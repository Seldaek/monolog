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

use Monolog\Logger;
use Monolog\Test\TestCase;

/**
 * @author Tristan Bessoussa <tristan.bessoussa@gmail.com>
 */
class DatadogFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\DatadogFormatter::format
     */
    public function testFormat()
    {
        $formatter = new DatadogFormatter('app_test', 'vps-host-1');
        $record = $this->getRecord();

        $formattedDecoded = json_decode($formatter->format($record), true);

        $this->assertArrayHasKey('ddsource', $formattedDecoded);
        $this->assertEquals('php', $formattedDecoded['ddsource']);

        $this->assertArrayHasKey('host', $formattedDecoded);
        $this->assertEquals('vps-host-1', $formattedDecoded['host']);

        $this->assertArrayHasKey('service', $formattedDecoded);
        $this->assertEquals('app_test', $formattedDecoded['service']);

        $this->assertArrayNotHasKey('level_name', $formattedDecoded);
        $this->assertArrayHasKey('status', $formattedDecoded);
        $this->assertEquals(Logger::getLevelName(Logger::WARNING), $formattedDecoded['status']);
    }
}
