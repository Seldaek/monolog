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

use Monolog\Level;
use Monolog\Test\TestCase;

class GitProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\GitProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new GitProcessor();
        $record = $processor($this->getRecord());

        $this->assertArrayHasKey('git', $record->extra);
        $this->assertTrue(!is_array($record->extra['git']['branch']));
    }

    /**
     * @covers Monolog\Processor\GitProcessor::__invoke
     */
    public function testProcessorWithLevel()
    {
        $processor = new GitProcessor(Level::Error);
        $record = $processor($this->getRecord());

        $this->assertArrayNotHasKey('git', $record->extra);
    }
}
