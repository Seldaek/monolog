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

class HostnameProcessorTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Processor\HostnameProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new HostnameProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('hostname', $record->extra);
        $this->assertIsString($record->extra['hostname']);
        $this->assertNotEmpty($record->extra['hostname']);
        $this->assertEquals(gethostname(), $record->extra['hostname']);
    }
}
