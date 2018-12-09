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

class RamseyUuidProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\RamseyUuidProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new RamseyUuidProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('uuid', $record['extra']);
    }

    /**
     * @covers Monolog\Processor\RamseyUuidProcessor::reset
     */
    public function testResetProcessor()
    {
        $processor = new RamseyUuidProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('uuid', $record['extra']);
        $u1 = $record['extra'];
        $processor->reset();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('uuid', $record['extra']);
        $u2 = $record['extra'];
        $this->assertNotSame($u1, $u2);
    }

    public function testGetUid()
    {
        $processor = new RamseyUuidProcessor();
        $this->assertSame(4, $processor->getUuid()->getVersion());
    }
}
