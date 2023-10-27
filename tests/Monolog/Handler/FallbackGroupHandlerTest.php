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

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Test\TestCase;

class FallbackGroupHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\FallbackGroupHandler::__construct
     * @covers Monolog\Handler\FallbackGroupHandler::handle
     */
    public function testHandle()
    {
        $testHandlerOne = new TestHandler();
        $testHandlerTwo = new TestHandler();
        $testHandlers = [$testHandlerOne, $testHandlerTwo];
        $handler = new FallbackGroupHandler($testHandlers);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));

        $this->assertCount(2, $testHandlerOne->getRecords());
        $this->assertCount(0, $testHandlerTwo->getRecords());
    }

    /**
     * @covers Monolog\Handler\FallbackGroupHandler::__construct
     * @covers Monolog\Handler\FallbackGroupHandler::handle
     */
    public function testHandleExceptionThrown()
    {
        $testHandlerOne = new ExceptionTestHandler();
        $testHandlerTwo = new TestHandler();
        $testHandlers = [$testHandlerOne, $testHandlerTwo];
        $handler = new FallbackGroupHandler($testHandlers);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));

        $this->assertCount(0, $testHandlerOne->getRecords());
        $this->assertCount(2, $testHandlerTwo->getRecords());
    }

    /**
     * @covers Monolog\Handler\FallbackGroupHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $testHandlerOne = new TestHandler();
        $testHandlerTwo = new TestHandler();
        $testHandlers = [$testHandlerOne, $testHandlerTwo];
        $handler = new FallbackGroupHandler($testHandlers);
        $handler->handleBatch([$this->getRecord(Level::Debug), $this->getRecord(Level::Info)]);
        $this->assertCount(2, $testHandlerOne->getRecords());
        $this->assertCount(0, $testHandlerTwo->getRecords());
    }

    /**
     * @covers Monolog\Handler\FallbackGroupHandler::handleBatch
     */
    public function testHandleBatchExceptionThrown()
    {
        $testHandlerOne = new ExceptionTestHandler();
        $testHandlerTwo = new TestHandler();
        $testHandlers = [$testHandlerOne, $testHandlerTwo];
        $handler = new FallbackGroupHandler($testHandlers);
        $handler->handleBatch([$this->getRecord(Level::Debug), $this->getRecord(Level::Info)]);
        $this->assertCount(0, $testHandlerOne->getRecords());
        $this->assertCount(2, $testHandlerTwo->getRecords());
    }

    /**
     * @covers Monolog\Handler\FallbackGroupHandler::isHandling
     */
    public function testIsHandling()
    {
        $testHandlers = [new TestHandler(Level::Error), new TestHandler(Level::Warning)];
        $handler = new FallbackGroupHandler($testHandlers);
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Error)));
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Warning)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Debug)));
    }

    /**
     * @covers Monolog\Handler\FallbackGroupHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test = new TestHandler();
        $handler = new FallbackGroupHandler([$test]);
        $handler->pushProcessor(function ($record) {
            $record->extra['foo'] = true;

            return $record;
        });
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }

    /**
     * @covers Monolog\Handler\FallbackGroupHandler::handleBatch
     */
    public function testHandleBatchUsesProcessors()
    {
        $testHandlerOne = new ExceptionTestHandler();
        $testHandlerTwo = new TestHandler();
        $testHandlers = [$testHandlerOne, $testHandlerTwo];
        $handler = new FallbackGroupHandler($testHandlers);
        $handler->pushProcessor(function ($record) {
            $record->extra['foo'] = true;

            return $record;
        });
        $handler->pushProcessor(function ($record) {
            $record->extra['foo2'] = true;

            return $record;
        });
        $handler->handleBatch([$this->getRecord(Level::Debug), $this->getRecord(Level::Info)]);
        $this->assertEmpty($testHandlerOne->getRecords());
        $this->assertTrue($testHandlerTwo->hasDebugRecords());
        $this->assertTrue($testHandlerTwo->hasInfoRecords());
        $this->assertCount(2, $testHandlerTwo->getRecords());
        $records = $testHandlerTwo->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
        $this->assertTrue($records[1]['extra']['foo']);
        $this->assertTrue($records[0]['extra']['foo2']);
        $this->assertTrue($records[1]['extra']['foo2']);
    }

    public function testProcessorsDoNotInterfereBetweenHandlers()
    {
        $t1 = new ExceptionTestHandler();
        $t2 = new TestHandler();
        $handler = new FallbackGroupHandler([$t1, $t2]);

        $t1->pushProcessor(function (LogRecord $record) {
            $record->extra['foo'] = 'bar';

            return $record;
        });
        $handler->handle($this->getRecord());

        self::assertSame([], $t2->getRecords()[0]->extra);
    }

    public function testProcessorsDoNotInterfereBetweenHandlersWithBatch()
    {
        $t1 = new ExceptionTestHandler();
        $t2 = new TestHandler();
        $handler = new FallbackGroupHandler([$t1, $t2]);

        $t1->pushProcessor(function (LogRecord $record) {
            $record->extra['foo'] = 'bar';

            return $record;
        });

        $handler->handleBatch([$this->getRecord()]);

        self::assertSame([], $t2->getRecords()[0]->extra);
    }
}
