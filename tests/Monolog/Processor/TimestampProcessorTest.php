<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Giuseppe Iannello <giuseppe.iannello@brokenloop.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\TestCase;

class TimestampProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\TimestampProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new TimestampProcessor();
        $record = $processor($this->getRecord());

        $this->assertArrayHasKey('timestamp', $record['extra']);
        // As processors are executed after the message has been generated,
        // the generated timestamp should be greater than the original datetime
        $this->assertGreaterThanOrEqual($record['datetime']->format('U.u'), $record['extra']['timestamp']);
        $this->assertStringMatchesFormat('%f', $record['extra']['timestamp']);
    }
}
