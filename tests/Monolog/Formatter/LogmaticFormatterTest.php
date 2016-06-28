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

/**
 * @author Julien Breux <julien.breux@gmail.com>
 */
class LogmaticFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\LogmaticFormatter::format
     */
    public function testFormat()
    {
        $formatter = new LogmaticFormatter();
        $formatter->setHostname('testHostname');
        $formatter->setAppname('testAppname');
        $record = $this->getRecord();
        $formatted_decoded = json_decode($formatter->format($record), true);
        $this->assertArrayHasKey('hostname', $formatted_decoded);
        $this->assertArrayHasKey('appname', $formatted_decoded);
        $this->assertEquals('testHostname', $formatted_decoded['hostname']);
        $this->assertEquals('testAppname', $formatted_decoded['appname']);
    }
}
