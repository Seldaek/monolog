<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\TestCase;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;

class AbstractHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\AbstractHandler::__construct
     * @covers Monolog\Handler\AbstractHandler::getLevel
     * @covers Monolog\Handler\AbstractHandler::setLevel
     * @covers Monolog\Handler\AbstractHandler::getBubble
     * @covers Monolog\Handler\AbstractHandler::setBubble
     * @covers Monolog\Handler\AbstractHandler::getFormatter
     * @covers Monolog\Handler\AbstractHandler::setFormatter
     */
    public function testConstructAndGetSet()
    {
        $handler = new TestHandler(Logger::WARNING, false);
        $this->assertEquals(Logger::WARNING, $handler->getLevel());
        $this->assertEquals(false, $handler->getBubble());

        $handler->setLevel(Logger::ERROR);
        $handler->setBubble(true);
        $handler->setFormatter($formatter = new LineFormatter);
        $this->assertEquals(Logger::ERROR, $handler->getLevel());
        $this->assertEquals(true, $handler->getBubble());
        $this->assertEquals($formatter, $handler->getFormatter());
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $handler = new TestHandler();
        $handler->handleBatch(array($this->getRecord(), $this->getRecord()));
        $this->assertEquals(2, count($handler->getRecords()));
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::isHandling
     */
    public function testIsHandling()
    {
        $handler = new TestHandler(Logger::WARNING, false);
        $this->assertTrue($handler->isHandling($this->getRecord()));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::DEBUG)));
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::getFormatter
     * @covers Monolog\Handler\AbstractHandler::getDefaultFormatter
     */
    public function testGetFormatterInitializesDefault()
    {
        $handler = new TestHandler();
        $this->assertInstanceOf('Monolog\Formatter\LineFormatter', $handler->getFormatter());
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::pushProcessor
     * @covers Monolog\Handler\AbstractHandler::popProcessor
     * @expectedException LogicException
     */
    public function testPushPopProcessor()
    {
        $logger = new TestHandler();
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;

        $logger->pushProcessor($processor1);
        $logger->pushProcessor($processor2);

        $this->assertEquals($processor2, $logger->popProcessor());
        $this->assertEquals($processor1, $logger->popProcessor());
        $logger->popProcessor();
    }

}
