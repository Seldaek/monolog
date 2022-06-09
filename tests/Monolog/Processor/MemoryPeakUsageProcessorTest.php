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

class MemoryPeakUsageProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\MemoryPeakUsageProcessor::__invoke
     * @covers Monolog\Processor\MemoryProcessor::formatBytes
     */
    public function testProcessor()
    {
        $processor = new MemoryPeakUsageProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('memory_peak_usage', $record->extra);
        $this->assertMatchesRegularExpression('#[0-9.]+ (M|K)?B$#', $record->extra['memory_peak_usage']);
    }

    /**
     * @covers Monolog\Processor\MemoryPeakUsageProcessor::__invoke
     * @covers Monolog\Processor\MemoryProcessor::formatBytes
     */
    public function testProcessorWithoutFormatting()
    {
        $processor = new MemoryPeakUsageProcessor(true, false);
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('memory_peak_usage', $record->extra);
        $this->assertIsInt($record->extra['memory_peak_usage']);
        $this->assertGreaterThan(0, $record->extra['memory_peak_usage']);
    }
}
