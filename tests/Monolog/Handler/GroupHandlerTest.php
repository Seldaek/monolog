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

use Monolog\LogRecord;
use Monolog\Level;

class GroupHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Handler\GroupHandler::__construct
     */
    public function testConstructorOnlyTakesHandler()
    {
        $this->expectException(\InvalidArgumentException::class);

        new GroupHandler([new TestHandler(), "foo"]);
    }

    /**
     * @covers Monolog\Handler\GroupHandler::__construct
     * @covers Monolog\Handler\GroupHandler::handle
     */
    public function testHandle()
    {
        $testHandlers = [new TestHandler(), new TestHandler()];
        $handler = new GroupHandler($testHandlers);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertCount(2, $test->getRecords());
        }
    }

    /**
     * @covers Monolog\Handler\GroupHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $testHandlers = [new TestHandler(), new TestHandler()];
        $handler = new GroupHandler($testHandlers);
        $handler->handleBatch([$this->getRecord(Level::Debug), $this->getRecord(Level::Info)]);
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertCount(2, $test->getRecords());
        }
    }

    /**
     * @covers Monolog\Handler\GroupHandler::isHandling
     */
    public function testIsHandling()
    {
        $testHandlers = [new TestHandler(Level::Error), new TestHandler(Level::Warning)];
        $handler = new GroupHandler($testHandlers);
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Error)));
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Warning)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Debug)));
    }

    /**
     * @covers Monolog\Handler\GroupHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test = new TestHandler();
        $handler = new GroupHandler([$test]);
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
     * @covers Monolog\Handler\GroupHandler::handle
     */
    public function testHandleBatchUsesProcessors()
    {
        $testHandlers = [new TestHandler(), new TestHandler()];
        $handler = new GroupHandler($testHandlers);
        $handler->pushProcessor(function ($record) {
            $record->extra['foo'] = true;

            return $record;
        });
        $handler->pushProcessor(function ($record) {
            $record->extra['foo2'] = true;

            return $record;
        });
        $handler->handleBatch([$this->getRecord(Level::Debug), $this->getRecord(Level::Info)]);
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

    public function testProcessorsDoNotInterfereBetweenHandlers()
    {
        $t1 = new TestHandler();
        $t2 = new TestHandler();
        $handler = new GroupHandler([$t1, $t2]);

        $t1->pushProcessor(function (LogRecord $record) {
            $record->extra['foo'] = 'bar';

            return $record;
        });
        $handler->handle($this->getRecord());

        self::assertSame([], $t2->getRecords()[0]->extra);
    }

    public function testProcessorsDoNotInterfereBetweenHandlersWithBatch()
    {
        $t1 = new TestHandler();
        $t2 = new TestHandler();
        $handler = new GroupHandler([$t1, $t2]);

        $t1->pushProcessor(function (LogRecord $record) {
            $record->extra['foo'] = 'bar';

            return $record;
        });

        $handler->handleBatch([$this->getRecord()]);

        self::assertSame([], $t2->getRecords()[0]->extra);
    }
}
