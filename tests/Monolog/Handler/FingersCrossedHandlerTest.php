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

class FingersCrossedHandlerTest extends TestCase
{
    public function testHandleBuffers()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue(count($test->getRecords()) === 3);
    }

    public function testHandleStopsBufferingAfterTrigger()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
    }

    public function testHandleRestartBufferingAfterReset()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->reset();
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
    }

    public function testHandleRestartBufferingAfterBeingTriggered()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Logger::WARNING, 0, false, false);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
    }

    public function testHandleBufferLimit()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Logger::WARNING, 2);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    public function testHandleWithCallback()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler(function($record, $handler) use ($test) {
                    return $test;
                });
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue(count($test->getRecords()) === 3);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWithBadCallbackThrowsException()
    {
        $handler = new FingersCrossedHandler(function($record, $handler) {
                    return 'foo';
                });
        $handler->handle($this->getRecord(Logger::WARNING));
    }
}
