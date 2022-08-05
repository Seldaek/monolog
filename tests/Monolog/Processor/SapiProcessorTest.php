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

class SapiProcessorTest extends TestCase
{
    /**
     * @covers Monolog\Processor\SapiProcessor::__invoke
     */
    public function testProcessor()
    {
        $processor = new SapiProcessor();

        $record = $processor($this->getRecord());

        $this->assertEquals(['SAPI' => 'cli'], $record->extra);
    }
}
