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

class WhatFailureGroupHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::__construct
     */
    public function testConstructorOnlyTakesHandler()
    {
        $this->expectException(\InvalidArgumentException::class);

        new WhatFailureGroupHandler([new TestHandler(), "foo"]);
    }

    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::__construct
     * @covers Monolog\Handler\WhatFailureGroupHandler::handle
     */
    public function testHandle()
    {
        $testHandlers = [new TestHandler(), new TestHandler()];
        $handler = new WhatFailureGroupHandler($testHandlers);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertCount(2, $test->getRecords());
        }
    }

    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $testHandlers = [new TestHandler(), new TestHandler()];
        $handler = new WhatFailureGroupHandler($testHandlers);
        $handler->handleBatch([$this->getRecord(Logger::DEBUG), $this->getRecord(Logger::INFO)]);
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertCount(2, $test->getRecords());
        }
    }

    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::isHandling
     */
    public function testIsHandling()
    {
        $testHandlers = [new TestHandler(Logger::ERROR), new TestHandler(Logger::WARNING)];
        $handler = new WhatFailureGroupHandler($testHandlers);
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::ERROR)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::WARNING)));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::DEBUG)));
    }

    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test = new TestHandler();
        $handler = new WhatFailureGroupHandler([$test]);
        $handler->pushProcessor(function ($record) {
            $record['extra']['foo'] = true;

            return $record;
        });
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }

    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::handleBatch
     */
    public function testHandleBatchUsesProcessors()
    {
        $testHandlers = array(new TestHandler(), new TestHandler());
        $handler = new WhatFailureGroupHandler($testHandlers);
        $handler->pushProcessor(function ($record) {
            $record['extra']['foo'] = true;

            return $record;
        });
        $handler->pushProcessor(function ($record) {
            $record['extra']['foo2'] = true;

            return $record;
        });
        $handler->handleBatch(array($this->getRecord(Logger::DEBUG), $this->getRecord(Logger::INFO)));
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertCount(2, $test->getRecords());
            $records = $test->getRecords();
            $this->assertTrue($records[0]['extra']['foo']);
            $this->assertTrue($records[1]['extra']['foo']);
            $this->assertTrue($records[0]['extra']['foo2']);
            $this->assertTrue($records[1]['extra']['foo2']);
        }
    }

    /**
     * @covers Monolog\Handler\WhatFailureGroupHandler::handle
     */
    public function testHandleException()
    {
        $test = new TestHandler();
        $exception = new ExceptionTestHandler();
        $handler = new WhatFailureGroupHandler([$exception, $test, $exception]);
        $handler->pushProcessor(function ($record) {
            $record['extra']['foo'] = true;

            return $record;
        });
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }
}
