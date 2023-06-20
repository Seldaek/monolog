<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\Test\TestCase;

/**
 * Injects sys_getloadavg in all records @see https://www.php.net/manual/en/function.sys-getloadavg.php
 *
 * @author Johan Vlaar <johan.vlaar.1994@gmail.com>
 */
class CPUUsageProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\CPUUsageProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new CPUUsageProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('cpu_usage', $record->extra);
        $this->assertIsFloat($record->extra['cpu_usage']);
    }

    /**
     * @covers Monolog\Processor\CPUUsageProcessor::__invoke
     */
    public function testProcessorWithInvalidAvgSystemLoad()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid average system load: `3`');
        new CPUUsageProcessor(3);
    }
}
