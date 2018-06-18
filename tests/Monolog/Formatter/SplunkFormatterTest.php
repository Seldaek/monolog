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

use Monolog\Test\TestCase;

class SplunkFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\SplunkFormatter::format
     */
    public function testFormat()
    {
        $formatter = new SplunkFormatter();
        $record = $this->getRecord();
        $formatted_decoded = json_decode($formatter->format($record), true);

        $this->assertArrayNotHasKey("datetime", $formatted_decoded);
        $this->assertEquals("timestamp", key($formatted_decoded));
        $this->assertEquals($record["datetime"]->format("Y-m-d H:i:s.u T"), $formatted_decoded["timestamp"]);
    }
}
