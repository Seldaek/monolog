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

use DateTimeInterface;
use Monolog\Test\MonologTestCase;
use function json_decode;

class GoogleCloudLoggingFormatterTest extends MonologTestCase
{
    /**
     * @test
     *
     * @covers \Monolog\Formatter\JsonFormatter
     * @covers \Monolog\Formatter\GoogleCloudLoggingFormatter::normalizeRecord
     */
    public function formatProvidesRfc3339Timestamps(): void
    {
        $formatter = new GoogleCloudLoggingFormatter();
        $record = $this->getRecord();

        $formatted_decoded = json_decode($formatter->format($record), true);
        $this->assertArrayNotHasKey("datetime", $formatted_decoded);
        $this->assertArrayHasKey("time", $formatted_decoded);
        $this->assertSame($record->datetime->format(DateTimeInterface::RFC3339_EXTENDED), $formatted_decoded["time"]);
    }

    /**
     * @test
     *
     * @covers \Monolog\Formatter\JsonFormatter
     * @covers \Monolog\Formatter\GoogleCloudLoggingFormatter::normalizeRecord
     */
    public function formatIntroducesLogSeverity(): void
    {
        $formatter = new GoogleCloudLoggingFormatter();
        $record = $this->getRecord();

        $formatted_decoded = json_decode($formatter->format($record), true);
        $this->assertArrayNotHasKey("level", $formatted_decoded);
        $this->assertArrayNotHasKey("level_name", $formatted_decoded);
        $this->assertArrayHasKey("severity", $formatted_decoded);
        $this->assertSame($record->level->getName(), $formatted_decoded["severity"]);
    }
}
