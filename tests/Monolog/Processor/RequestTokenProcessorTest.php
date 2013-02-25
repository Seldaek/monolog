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

class RequestTokenProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\RequestTokenProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new RequestTokenProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('request_token', $record['extra']);
    }
}
