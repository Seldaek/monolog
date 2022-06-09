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
use Monolog\Test\TestCase;

class DeduplicationHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\DeduplicationHandler::flush
     */
    public function testFlushPassthruIfAllRecordsUnderTrigger()
    {
        $test = new TestHandler();
        @unlink(sys_get_temp_dir().'/monolog_dedup.log');
        $handler = new DeduplicationHandler($test, sys_get_temp_dir().'/monolog_dedup.log', Level::Debug);

        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));

        $handler->flush();

        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\DeduplicationHandler::flush
     * @covers Monolog\Handler\DeduplicationHandler::appendRecord
     */
    public function testFlushPassthruIfEmptyLog()
    {
        $test = new TestHandler();
        @unlink(sys_get_temp_dir().'/monolog_dedup.log');
        $handler = new DeduplicationHandler($test, sys_get_temp_dir().'/monolog_dedup.log', Level::Debug);

        $handler->handle($this->getRecord(Level::Error, 'Foo:bar'));
        $handler->handle($this->getRecord(Level::Critical, "Foo\nbar"));

        $handler->flush();

        $this->assertTrue($test->hasErrorRecords());
        $this->assertTrue($test->hasCriticalRecords());
        $this->assertFalse($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\DeduplicationHandler::flush
     * @covers Monolog\Handler\DeduplicationHandler::appendRecord
     * @covers Monolog\Handler\DeduplicationHandler::isDuplicate
     * @depends testFlushPassthruIfEmptyLog
     */
    public function testFlushSkipsIfLogExists()
    {
        $test = new TestHandler();
        $handler = new DeduplicationHandler($test, sys_get_temp_dir().'/monolog_dedup.log', Level::Debug);

        $handler->handle($this->getRecord(Level::Error, 'Foo:bar'));
        $handler->handle($this->getRecord(Level::Critical, "Foo\nbar"));

        $handler->flush();

        $this->assertFalse($test->hasErrorRecords());
        $this->assertFalse($test->hasCriticalRecords());
        $this->assertFalse($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\DeduplicationHandler::flush
     * @covers Monolog\Handler\DeduplicationHandler::appendRecord
     * @covers Monolog\Handler\DeduplicationHandler::isDuplicate
     * @depends testFlushPassthruIfEmptyLog
     */
    public function testFlushPassthruIfLogTooOld()
    {
        $test = new TestHandler();
        $handler = new DeduplicationHandler($test, sys_get_temp_dir().'/monolog_dedup.log', Level::Debug);

        $record = $this->getRecord(Level::Error, datetime: new \DateTimeImmutable('+62seconds'));
        $handler->handle($record);
        $record = $this->getRecord(Level::Critical, datetime: new \DateTimeImmutable('+62seconds'));
        $handler->handle($record);

        $handler->flush();

        $this->assertTrue($test->hasErrorRecords());
        $this->assertTrue($test->hasCriticalRecords());
        $this->assertFalse($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\DeduplicationHandler::flush
     * @covers Monolog\Handler\DeduplicationHandler::appendRecord
     * @covers Monolog\Handler\DeduplicationHandler::isDuplicate
     * @covers Monolog\Handler\DeduplicationHandler::collectLogs
     */
    public function testGcOldLogs()
    {
        $test = new TestHandler();
        @unlink(sys_get_temp_dir().'/monolog_dedup.log');
        $handler = new DeduplicationHandler($test, sys_get_temp_dir().'/monolog_dedup.log', Level::Debug);

        // handle two records from yesterday, and one recent
        $record = $this->getRecord(Level::Error, datetime: new \DateTimeImmutable('-1day -10seconds'));
        $handler->handle($record);
        $record2 = $this->getRecord(Level::Critical, datetime: new \DateTimeImmutable('-1day -10seconds'));
        $handler->handle($record2);
        $record3 = $this->getRecord(Level::Critical, datetime: new \DateTimeImmutable('-30seconds'));
        $handler->handle($record3);

        // log is written as none of them are duplicate
        $handler->flush();
        $this->assertSame(
            $record->datetime->getTimestamp() . ":ERROR:test\n" .
            $record2['datetime']->getTimestamp() . ":CRITICAL:test\n" .
            $record3['datetime']->getTimestamp() . ":CRITICAL:test\n",
            file_get_contents(sys_get_temp_dir() . '/monolog_dedup.log')
        );
        $this->assertTrue($test->hasErrorRecords());
        $this->assertTrue($test->hasCriticalRecords());
        $this->assertFalse($test->hasWarningRecords());

        // clear test handler
        $test->clear();
        $this->assertFalse($test->hasErrorRecords());
        $this->assertFalse($test->hasCriticalRecords());

        // log new records, duplicate log gets GC'd at the end of this flush call
        $handler->handle($record = $this->getRecord(Level::Error));
        $handler->handle($record2 = $this->getRecord(Level::Critical));
        $handler->flush();

        // log should now contain the new errors and the previous one that was recent enough
        $this->assertSame(
            $record3['datetime']->getTimestamp() . ":CRITICAL:test\n" .
            $record->datetime->getTimestamp() . ":ERROR:test\n" .
            $record2['datetime']->getTimestamp() . ":CRITICAL:test\n",
            file_get_contents(sys_get_temp_dir() . '/monolog_dedup.log')
        );
        $this->assertTrue($test->hasErrorRecords());
        $this->assertTrue($test->hasCriticalRecords());
        $this->assertFalse($test->hasWarningRecords());
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(sys_get_temp_dir().'/monolog_dedup.log');
    }
}
