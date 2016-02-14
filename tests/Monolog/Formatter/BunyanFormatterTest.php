<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Wolfram Huesken <woh@m18.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;
use Monolog\TestCase;

class BunyanFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\BunyanFormatter::__construct
     * @covers Monolog\Formatter\BunyanFormatter::getBatchMode
     * @covers Monolog\Formatter\BunyanFormatter::isAppendingNewlines
     */
    public function testConstruct()
    {
        $formatter = new BunyanFormatter();
        $this->assertEquals(BunyanFormatter::BATCH_MODE_JSON, $formatter->getBatchMode());
        $this->assertEquals(true, $formatter->isAppendingNewlines());

        $formatter = new BunyanFormatter(BunyanFormatter::BATCH_MODE_NEWLINES, false);
        $this->assertEquals(BunyanFormatter::BATCH_MODE_NEWLINES, $formatter->getBatchMode());
        $this->assertEquals(false, $formatter->isAppendingNewlines());
    }

    /**
     * @covers Monolog\Formatter\BunyanFormatter::format
     */
    public function testFormat()
    {
        $formatter = new BunyanFormatter();
        $record = $this->getRecord();

        $result = $formatter->format($record);
        $resultArr = json_decode($result, true);

        // Has EOL
        $this->assertEquals(PHP_EOL, substr($result, -1, 1));

        // Log Level Mapping
        $this->assertEquals($resultArr['level'], $formatter->getBunyanLogLevel($record['level']));

        // Message
        $this->assertEquals($resultArr['msg'], $record['message']);

        // Name
        $this->assertEquals($resultArr['name'], $record['channel']);

        // Version
        $this->assertEquals(BunyanFormatter::BUNYAN_VERSION, $resultArr['v']);

        // Hostname
        $this->assertEquals(gethostname(), $resultArr['hostname']);

        // PID
        $this->assertEquals(getmypid(), $resultArr['pid']);

        $formatter = new BunyanFormatter(BunyanFormatter::BATCH_MODE_JSON, false);
        $result = $formatter->format($record);

        // No EOL
        $this->assertNotEquals(PHP_EOL, substr($result, -1, 1));
    }

    /**
     * @covers Monolog\Formatter\BunyanFormatter::formatBatch
     * @covers Monolog\Formatter\BunyanFormatter::formatBatchJson
     */
    public function testFormatBatch()
    {
        $formatter = new BunyanFormatter();
        $records = array(
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        );

        $result = $formatter->formatBatch($records);
        $resultArr = json_decode($formatter->formatBatch($records), true);

        $this->assertCount(2, $resultArr);

        // No EOL
        $this->assertNotEquals(PHP_EOL, substr($result, -1, 1));

        // Is warning
        $record = array_shift($resultArr);
        $this->assertEquals(BunyanFormatter::LEVEL_WARN, $record['level']);

        // Is debug
        $record = array_shift($resultArr);
        $this->assertEquals(BunyanFormatter::LEVEL_DEBUG, $record['level']);
    }

    /**
     * @covers Monolog\Formatter\BunyanFormatter::formatBatch
     * @covers Monolog\Formatter\BunyanFormatter::formatBatchNewlines
     */
    public function testFormatBatchNewlines()
    {
        $formatter = new BunyanFormatter(BunyanFormatter::BATCH_MODE_NEWLINES);
        $records = $expected = array(
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        );

        $result = $formatter->formatBatch($records);
        $resultArr = explode(PHP_EOL, $result);

        $this->assertEquals(1, substr_count($result, "\n"));
        $this->assertCount(2, $resultArr);

        // Is warning
        $record = json_decode(array_shift($resultArr), true);
        $this->assertEquals(BunyanFormatter::LEVEL_WARN, $record['level']);

        // Is debug
        $record = json_decode(array_shift($resultArr), true);
        $this->assertEquals(BunyanFormatter::LEVEL_DEBUG, $record['level']);
    }
}
