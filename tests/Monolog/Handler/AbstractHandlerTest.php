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
use Monolog\Logger;

class AbstractHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\AbstractHandler::__construct
     * @covers Monolog\Handler\AbstractHandler::getLevel
     * @covers Monolog\Handler\AbstractHandler::setLevel
     * @covers Monolog\Handler\AbstractHandler::getBubble
     * @covers Monolog\Handler\AbstractHandler::setBubble
     */
    public function testConstructAndGetSet()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler', [Logger::WARNING, false]);
        $this->assertEquals(Logger::WARNING, $handler->getLevel());
        $this->assertEquals(false, $handler->getBubble());

        $handler->setLevel(Logger::ERROR);
        $handler->setBubble(true);
        $this->assertEquals(Logger::ERROR, $handler->getLevel());
        $this->assertEquals(true, $handler->getBubble());
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler');
        $handler->expects($this->exactly(2))
            ->method('handle');
        $handler->handleBatch([$this->getRecord(), $this->getRecord()]);
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::isHandling
     */
    public function testIsHandling()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler', [Logger::WARNING, false]);
        $this->assertTrue($handler->isHandling($this->getRecord()));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::DEBUG)));
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::__construct
     */
    public function testHandlesPsrStyleLevels()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler', ['warning', false]);
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::DEBUG)));
        $handler->setLevel('debug');
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::DEBUG)));
    }
}
