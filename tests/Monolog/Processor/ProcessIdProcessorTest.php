<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\TestCase;

class ProcessIdProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\ProcessIdProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new ProcessIdProcessor();
        $record    = $processor($this->getRecord());
        self::assertArrayHasKey('process_id', $record['extra']);
        self::assertInternalType('int', $record['extra']['process_id']);
        self::assertGreaterThan(0, $record['extra']['process_id']);
        self::assertEquals(getmypid(), $record['extra']['process_id']);
    }
}
