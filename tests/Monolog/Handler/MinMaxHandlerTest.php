<?php

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\TestCase;

/**
 * Unit tests for minMaxHandler
 *
 * @author Hennadiy Verkh
 */
class MinMaxHandlerTest extends TestCase
{

    /**
     * @covers Monolog\Handler\MinMaxHandler::isHandling
     */
    public function testIsHandling()
    {
        $test    = new TestHandler();
        $handler = new MinMaxHandler($test, Logger::INFO, Logger::NOTICE);
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::DEBUG)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::INFO)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::NOTICE)));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::WARNING)));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::ERROR)));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::CRITICAL)));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::ALERT)));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::EMERGENCY)));
    }

    /**
     * @covers Monolog\Handler\MinMaxHandler::handle
     */
    public function testHandleProcessOnlyNeededLevels()
    {
        $test    = new TestHandler();
        $handler = new MinMaxHandler($test, Logger::INFO, Logger::NOTICE);

        $handler->handle($this->getRecord(Logger::DEBUG));
        $this->assertFalse($test->hasDebugRecords());

        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertTrue($test->hasInfoRecords());
        $handler->handle($this->getRecord(Logger::NOTICE));
        $this->assertTrue($test->hasNoticeRecords());

        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertFalse($test->hasWarningRecords());
        $handler->handle($this->getRecord(Logger::ERROR));
        $this->assertFalse($test->hasErrorRecords());
        $handler->handle($this->getRecord(Logger::CRITICAL));
        $this->assertFalse($test->hasCriticalRecords());
        $handler->handle($this->getRecord(Logger::ALERT));
        $this->assertFalse($test->hasAlertRecords());
        $handler->handle($this->getRecord(Logger::EMERGENCY));
        $this->assertFalse($test->hasEmergencyRecords());
    }

    /**
     * @covers Monolog\Handler\MinMaxHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test    = new TestHandler();
        $handler = new MinMaxHandler($test, Logger::DEBUG, Logger::EMERGENCY);
        $handler->pushProcessor(
            function ($record) {
                $record['extra']['foo'] = true;

                return $record;
            }
        );
        $handler->handle($this->getRecord(Logger::WARNING));
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }

    /**
     * @covers Monolog\Handler\MinMaxHandler::handle
     */
    public function testHandleRespectsBubble()
    {
        $test = new TestHandler();

        $handler = new MinMaxHandler($test, Logger::INFO, Logger::NOTICE, false);
        $this->assertTrue($handler->handle($this->getRecord(Logger::INFO)));
        $this->assertFalse($handler->handle($this->getRecord(Logger::WARNING)));

        $handler = new MinMaxHandler($test, Logger::INFO, Logger::NOTICE, true);
        $this->assertFalse($handler->handle($this->getRecord(Logger::INFO)));
        $this->assertFalse($handler->handle($this->getRecord(Logger::WARNING)));
    }
}
