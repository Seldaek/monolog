<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Test\TestCase;
use Monolog\Level;
use Monolog\Processor\WebProcessor;
use Monolog\Formatter\LineFormatter;

class AbstractProcessingHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\FormattableHandlerTrait::getFormatter
     * @covers Monolog\Handler\FormattableHandlerTrait::setFormatter
     */
    public function testConstructAndGetSet()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler', [Level::Warning, false]);
        $handler->setFormatter($formatter = new LineFormatter);
        $this->assertSame($formatter, $handler->getFormatter());
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleLowerLevelMessage()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler', [Level::Warning, true]);
        $this->assertFalse($handler->handle($this->getRecord(Level::Debug)));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleBubbling()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler', [Level::Debug, true]);
        $this->assertFalse($handler->handle($this->getRecord()));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleNotBubbling()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler', [Level::Debug, false]);
        $this->assertTrue($handler->handle($this->getRecord()));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleIsFalseWhenNotHandled()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler', [Level::Warning, false]);
        $this->assertTrue($handler->handle($this->getRecord()));
        $this->assertFalse($handler->handle($this->getRecord(Level::Debug)));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::processRecord
     */
    public function testProcessRecord()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler');
        $handler->pushProcessor(new WebProcessor([
            'REQUEST_URI' => '',
            'REQUEST_METHOD' => '',
            'REMOTE_ADDR' => '',
            'SERVER_NAME' => '',
            'UNIQUE_ID' => '',
        ]));
        $handledRecord = null;
        $handler->expects($this->once())
            ->method('write')
            ->will($this->returnCallback(function ($record) use (&$handledRecord) {
                $handledRecord = $record;
            }))
        ;
        $handler->handle($this->getRecord());
        $this->assertEquals(6, count($handledRecord['extra']));
    }

    /**
     * @covers Monolog\Handler\ProcessableHandlerTrait::pushProcessor
     * @covers Monolog\Handler\ProcessableHandlerTrait::popProcessor
     */
    public function testPushPopProcessor()
    {
        $logger = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler');
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;

        $logger->pushProcessor($processor1);
        $logger->pushProcessor($processor2);

        $this->assertEquals($processor2, $logger->popProcessor());
        $this->assertEquals($processor1, $logger->popProcessor());

        $this->expectException(\LogicException::class);

        $logger->popProcessor();
    }

    /**
     * @covers Monolog\Handler\ProcessableHandlerTrait::pushProcessor
     */
    public function testPushProcessorWithNonCallable()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler');

        $this->expectException(\TypeError::class);

        $handler->pushProcessor(new \stdClass());
    }

    /**
     * @covers Monolog\Handler\FormattableHandlerTrait::getFormatter
     * @covers Monolog\Handler\FormattableHandlerTrait::getDefaultFormatter
     */
    public function testGetFormatterInitializesDefault()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractProcessingHandler');
        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }
}
