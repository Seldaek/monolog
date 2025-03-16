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

class ProcessIdProcessorTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Processor\ProcessIdProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new ProcessIdProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('process_id', $record->extra);
        $this->assertIsInt($record->extra['process_id']);
        $this->assertGreaterThan(0, $record->extra['process_id']);
        $this->assertEquals(getmypid(), $record->extra['process_id']);
    }
}
