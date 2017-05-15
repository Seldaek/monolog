<?php

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\TestCase;

class CallbackFilterHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\CallbackFilterHandler::isHandling
     */
    public function testIsHandling()
    {
        $filters = array();
        $test    = new TestHandler();
        $handler = new CallbackFilterHandler($test, $filters);

        $this->assertTrue($handler->isHandling($this->getRecord(Logger::DEBUG)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::INFO)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::NOTICE)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::WARNING)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::ERROR)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::CRITICAL)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::ALERT)));
        $this->assertTrue($handler->isHandling($this->getRecord(Logger::EMERGENCY)));
    }

    /**
     * @covers Monolog\Handler\CallbackFilterHandler::handle
     */
    public function testHandleProcessOnlyNeededLevels()
    {
        $filter1 = function($record) {
            if ($record['level'] == Logger::INFO) {
                return true;
            }
            if ($record['level'] == Logger::NOTICE) {
                return true;
            }
            return false;
        };
        $filters = array($filter1);

        $test    = new TestHandler();
        $handler = new CallbackFilterHandler($test, $filters);

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
     * @covers Monolog\Handler\CallbackFilterHandler::handle
     */
    public function testHandleProcessAllMatchingRules()
    {
        $filter1 = function($record) {
            return ($record['level'] == Logger::NOTICE);
        };
        $filter2 = function($record) {
            return (preg_match('/message/', $record['message']) === 1);
        };
        $filters = array($filter1, $filter2);

        $test    = new TestHandler();
        $handler = new CallbackFilterHandler($test, $filters);

        $handler->handle($this->getRecord(Logger::DEBUG));
        $this->assertFalse($test->hasDebugRecords());
                
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertFalse($test->hasInfoRecords());

        $handler->handle($this->getRecord(Logger::NOTICE));
        $this->assertFalse($test->hasNoticeRecords());

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
     * @covers Monolog\Handler\CallbackFilterHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $filter1 = function($record) {
            return ($record['level'] == Logger::INFO);
        };
        $filter2 = function($record) {
            return (preg_match('/information/', $record['message']) === 1);
        };
        $filters = array($filter1, $filter2);
        
        $test    = new TestHandler();
        $handler = new CallbackFilterHandler($test, $filters);
        
        $records = $this->getMultipleRecords();
        
        $handler->handleBatch($records);
        
        $this->assertFalse($test->hasDebugRecords());
        
        $this->assertTrue($test->hasInfoRecords());

        $this->assertFalse($test->hasNoticeRecords());
        $this->assertFalse($test->hasWarningRecords());
        $this->assertFalse($test->hasErrorRecords());
        $this->assertFalse($test->hasCriticalRecords());
        $this->assertFalse($test->hasAlertRecords());
        $this->assertFalse($test->hasEmergencyRecords());        
    }
    
    /**
     * @covers Monolog\Handler\CallbackFilterHandler::handle
     * @covers Monolog\Handler\CallbackFilterHandler::pushProcessor
     */
    public function testHandleUsesProcessors()
    {
        $filter1 = function($record) {
            if ($record['level'] == Logger::DEBUG) {
                return true;
            }
            if ($record['level'] == Logger::WARNING) {
                return true;
            }
            return false;
        };
        $filters = array($filter1);

        $test    = new TestHandler();
        $handler = new CallbackFilterHandler($test, $filters);
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
     * @covers Monolog\Handler\CallbackFilterHandler::handle
     */
    public function testHandleRespectsBubble()
    {
        $filter1 = function($record) {
            if ($record['level'] == Logger::INFO) {
                return true;
            }
            if ($record['level'] == Logger::NOTICE) {
                return true;
            }
            return false;
        };
        $filters = array($filter1);

        $test = new TestHandler();

        $handler = new CallbackFilterHandler($test, $filters, false);
        $this->assertTrue($handler->handle($this->getRecord(Logger::INFO)));
        $this->assertFalse($handler->handle($this->getRecord(Logger::WARNING)));

        $handler = new CallbackFilterHandler($test, $filters, true);
        $this->assertFalse($handler->handle($this->getRecord(Logger::INFO)));
        $this->assertFalse($handler->handle($this->getRecord(Logger::WARNING)));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testHandleWithBadFilterThrowsException()
    {
        $filters = array(false);
        $test    = new TestHandler();
        $handler = new CallbackFilterHandler($test, $filters);
    }
}
