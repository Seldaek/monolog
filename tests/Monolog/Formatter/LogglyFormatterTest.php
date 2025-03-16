<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Test\MonologTestCase;

class LogglyFormatterTest extends MonologTestCase
{
    /**
     * @covers Monolog\Formatter\LogglyFormatter::__construct
     */
    public function testConstruct()
    {
        $formatter = new LogglyFormatter();
        $this->assertEquals(LogglyFormatter::BATCH_MODE_NEWLINES, $formatter->getBatchMode());
        $formatter = new LogglyFormatter(LogglyFormatter::BATCH_MODE_JSON);
        $this->assertEquals(LogglyFormatter::BATCH_MODE_JSON, $formatter->getBatchMode());
    }

    /**
     * @covers Monolog\Formatter\LogglyFormatter::format
     */
    public function testFormat()
    {
        $formatter = new LogglyFormatter();
        $record = $this->getRecord();
        $formatted_decoded = json_decode($formatter->format($record), true);
        $this->assertArrayNotHasKey("datetime", $formatted_decoded);
        $this->assertArrayHasKey("timestamp", $formatted_decoded);
        $this->assertEquals($record->datetime->format('Y-m-d\TH:i:s.uO'), $formatted_decoded["timestamp"]);
    }
}
