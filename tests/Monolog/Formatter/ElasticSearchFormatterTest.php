<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;

class ElasticSearchFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Monolog\Formatter\ElasticSearchFormatter::__construct
     * @covers Monolog\Formatter\ElasticSearchFormatter::format
     * @covers Monolog\Formatter\ElasticSearchFormatter::getDocument
     */
    public function testFormat()
    {
        // Test log message
        $msg = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['foo' => 7, 'bar', 'class' => new \stdClass],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        // Expected values
        $expected = $msg;
        $expected['datetime'] = '1970-01-01T00:00:00+0000';
        $expected['context'] = [
            'class' => ['stdClass' => []],
            'foo' => 7,
            0 => 'bar',
        ];

        // Format log message
        $formatter = new ElasticSearchFormatter('my_index', 'doc_type');
        $doc = $formatter->format($msg);
        $this->assertInternalType('array', $doc);

        // Record parameters
        $this->assertEquals('my_index', $doc['_index']);
        $this->assertEquals('doc_type', $doc['_type']);

        // Record data values
        foreach (array_keys($expected) as $key) {
            $this->assertEquals($expected[$key], $doc[$key]);
        }
    }

    /**
     * @covers Monolog\Formatter\ElasticSearchFormatter::getIndex
     * @covers Monolog\Formatter\ElasticSearchFormatter::getType
     */
    public function testGetters()
    {
        $formatter = new ElasticSearchFormatter('my_index', 'doc_type');
        $this->assertEquals('my_index', $formatter->getIndex());
        $this->assertEquals('doc_type', $formatter->getType());
    }
}
