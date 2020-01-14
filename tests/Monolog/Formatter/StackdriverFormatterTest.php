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

class StackdriverFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\StackdriverFormatter::format
     */
    public function testFormat()
    {
        $formatter = new StackdriverFormatter();
        $record = $this->getRecord();
        $recordDecoded = json_decode($formatter->format($record), true);
        $this->assertEquals($record['level_name'], $recordDecoded['severity']);
        $this->assertArrayNotHasKey('level', $recordDecoded);
        $this->assertArrayNotHasKey('level_name', $recordDecoded);
    }
}
