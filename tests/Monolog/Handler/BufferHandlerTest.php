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

use Monolog\Logger;
use Monolog\TestCase;

class BufferHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\BufferHandler::__construct
     * @covers Monolog\Handler\BufferHandler::handle
     * @covers Monolog\Handler\BufferHandler::close
     */
    public function testHandleBuffers()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        self::assertFalse($test->hasDebugRecords());
        self::assertFalse($test->hasInfoRecords());
        $handler->close();
        self::assertTrue($test->hasInfoRecords());
        self::assertTrue(count($test->getRecords()) === 2);
    }

    /**
     * @covers Monolog\Handler\BufferHandler::close
     * @covers Monolog\Handler\BufferHandler::flush
     */
    public function testDestructPropagatesRecords()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->__destruct();
        self::assertTrue($test->hasWarningRecords());
        self::assertTrue($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleBufferLimit()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test, 2);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->close();
        self::assertTrue($test->hasWarningRecords());
        self::assertTrue($test->hasInfoRecords());
        self::assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleBufferLimitWithFlushOnOverflow()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test, 3, Logger::DEBUG, true, true);

        // send two records
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        self::assertFalse($test->hasDebugRecords());
        self::assertCount(0, $test->getRecords());

        // overflow
        $handler->handle($this->getRecord(Logger::INFO));
        self::assertTrue($test->hasDebugRecords());
        self::assertCount(3, $test->getRecords());

        // should buffer again
        $handler->handle($this->getRecord(Logger::WARNING));
        self::assertCount(3, $test->getRecords());

        $handler->close();
        self::assertCount(5, $test->getRecords());
        self::assertTrue($test->hasWarningRecords());
        self::assertTrue($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleLevel()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test, 0, Logger::INFO);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->close();
        self::assertTrue($test->hasWarningRecords());
        self::assertTrue($test->hasInfoRecords());
        self::assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::flush
     */
    public function testFlush()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test, 0);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->flush();
        self::assertTrue($test->hasInfoRecords());
        self::assertTrue($test->hasDebugRecords());
        self::assertFalse($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test    = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->pushProcessor(
            function ($record) {
                $record['extra']['foo'] = true;

                return $record;
            }
        );
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->flush();
        self::assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        self::assertTrue($records[0]['extra']['foo']);
    }
}
