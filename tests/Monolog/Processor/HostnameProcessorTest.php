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

class HostnameProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\HostnameProcessor
     */
    public function testProcessor()
    {
        $processor = new HostnameProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('hostname', $record['extra']);
        
        $hostname = php_uname('n');
        $this->assertSame($hostname, $record['extra']['hostname']);
    }
}
