<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jonathan A. Schweder <jonathanschweder@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\Test\TestCase;

class MercurialProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\MercurialProcessor::__invoke
     */
    public function testProcessor()
    {
        `hg init`;
        $processor = new MercurialProcessor();
        $record = $processor($this->getRecord());

        $this->assertArrayHasKey('hg', $record['extra']);
        $this->assertTrue(!is_array($record['extra']['hg']['branch']));
        $this->assertTrue(!is_array($record['extra']['hg']['revision']));
    }
}
