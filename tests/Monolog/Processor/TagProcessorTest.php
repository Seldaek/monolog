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

class TagProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\TagProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new TagProcessor();
        $record = $processor($this->getRecord());
        $this->assertArrayHasKey('tags', $record);
    }
}
