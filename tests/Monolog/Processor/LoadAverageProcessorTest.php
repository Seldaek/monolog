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

class LoadAverageProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\LoadAverageProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new LoadAverageProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('load_average', $record->extra);
        $this->assertIsFloat($record->extra['load_average']);
    }

    /**
     * @covers Monolog\Processor\LoadAverageProcessor::__invoke
     */
    public function testProcessorWithInvalidAvgSystemLoad()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid average system load: `3`');
        new LoadAverageProcessor(3);
    }
}
