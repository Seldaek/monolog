<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;
use Monolog\TestCase;

class JsonFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\JsonFormatter::__construct
     * @covers Monolog\Formatter\JsonFormatter::getBatchMode
     * @covers Monolog\Formatter\JsonFormatter::isAppendingNewlines
     */
    public function testConstruct()
    {
        $formatter = new JsonFormatter();
        $this->assertEquals(JsonFormatter::BATCH_MODE_JSON, $formatter->getBatchMode());
        $this->assertEquals(true, $formatter->isAppendingNewlines());
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, false);
        $this->assertEquals(JsonFormatter::BATCH_MODE_NEWLINES, $formatter->getBatchMode());
        $this->assertEquals(false, $formatter->isAppendingNewlines());
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::format
     */
    public function testFormat()
    {
        $formatter = new JsonFormatter();
        $record = $this->getRecord();
        $this->assertEquals(json_encode($record)."\n", $formatter->format($record));

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $record = $this->getRecord();
        $this->assertEquals(json_encode($record), $formatter->format($record));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::formatBatch
     * @covers Monolog\Formatter\JsonFormatter::formatBatchJson
     */
    public function testFormatBatch()
    {
        $formatter = new JsonFormatter();
        $records = array(
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        );
        $this->assertEquals(json_encode($records), $formatter->formatBatch($records));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::formatBatch
     * @covers Monolog\Formatter\JsonFormatter::formatBatchNewlines
     */
    public function testFormatBatchNewlines()
    {

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES);
        $records = $expected = array(
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        );
        array_walk($expected, function (&$value, $key) {
            $value = json_encode($value);
        });
        $this->assertEquals(implode("\n", $expected), $formatter->formatBatch($records));
    }
}
