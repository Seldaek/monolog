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

class BufferHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\BufferHandler::__construct
     * @covers Monolog\Handler\BufferHandler::handle
     * @covers Monolog\Handler\BufferHandler::close
     */
    public function testHandleBuffers()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->close();
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue(count($test->getRecords()) === 2);
    }

    /**
     * @covers Monolog\Handler\BufferHandler::close
     * @covers Monolog\Handler\BufferHandler::flush
     */
    public function testDestructPropagatesRecords()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->__destruct();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleBufferLimit()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 2);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleBufferLimitWithFlushOnOverflow()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 3, Logger::DEBUG, true, true);

        // send two records
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertCount(0, $test->getRecords());

        // overflow
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertTrue($test->hasDebugRecords());
        $this->assertCount(3, $test->getRecords());

        // should buffer again
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertCount(3, $test->getRecords());

        $handler->close();
        $this->assertCount(5, $test->getRecords());
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleLevel()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 0, Logger::INFO);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::flush
     */
    public function testFlush()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 0);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->flush();
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasWarningRecords());
    }
}
