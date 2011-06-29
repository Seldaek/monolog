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
use Monolog\Processor\WebProcessor;

class AbstractProcessingHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleLowerLevelMessage()
    {
        $handler = new TestHandler(Logger::WARNING, true);
        $this->assertFalse($handler->handle($this->getRecord(Logger::DEBUG)));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleBubbling()
    {
        $handler = new TestHandler(Logger::DEBUG, true);
        $this->assertFalse($handler->handle($this->getRecord()));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleNotBubbling()
    {
        $handler = new TestHandler(Logger::DEBUG, false);
        $this->assertTrue($handler->handle($this->getRecord()));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::handle
     */
    public function testHandleIsFalseWhenNotHandled()
    {
        $handler = new TestHandler(Logger::WARNING, false);
        $this->assertTrue($handler->handle($this->getRecord()));
        $this->assertFalse($handler->handle($this->getRecord(Logger::DEBUG)));
    }

    /**
     * @covers Monolog\Handler\AbstractProcessingHandler::processRecord
     */
    public function testProcessRecord()
    {
        $handler = new TestHandler();
        $handler->pushProcessor(new WebProcessor(array(
            'REQUEST_URI' => '',
            'REQUEST_METHOD' => '',
            'REMOTE_ADDR' => '',
        )));
        $handler->handle($this->getRecord());
        list($record) = $handler->getRecords();
        $this->assertEquals(3, count($record['extra']));
    }
}
