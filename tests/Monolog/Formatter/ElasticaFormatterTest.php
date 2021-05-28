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

class ElasticaFormatterTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        if (!class_exists("Elastica\Document")) {
            $this->markTestSkipped("ruflin/elastica not installed");
        }
    }

    /**
     * @covers Monolog\Formatter\ElasticaFormatter::__construct
     * @covers Monolog\Formatter\ElasticaFormatter::format
     * @covers Monolog\Formatter\ElasticaFormatter::getDocument
     */
    public function testFormat()
    {
        // test log message
        $msg = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['foo' => 7, 'bar', 'class' => new \stdClass],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        // expected values
        $expected = $msg;
        $expected['datetime'] = '1970-01-01T00:00:00.000000+00:00';
        $expected['context'] = [
            'class' => ['stdClass' => []],
            'foo' => 7,
            0 => 'bar',
        ];

        // format log message
        $formatter = new ElasticaFormatter('my_index', 'doc_type');
        $doc = $formatter->format($msg);
        $this->assertInstanceOf('Elastica\Document', $doc);

        // Document parameters
        $this->assertEquals('my_index', $doc->getIndex());
        if (method_exists($doc, 'getType')) {
            $this->assertEquals('doc_type', $doc->getType());
        }

        // Document data values
        $data = $doc->getData();
        foreach (array_keys($expected) as $key) {
            $this->assertEquals($expected[$key], $data[$key]);
        }
    }

    /**
     * @covers Monolog\Formatter\ElasticaFormatter::getIndex
     * @covers Monolog\Formatter\ElasticaFormatter::getType
     */
    public function testGetters()
    {
        $formatter = new ElasticaFormatter('my_index', 'doc_type');
        $this->assertEquals('my_index', $formatter->getIndex());
        $this->assertEquals('doc_type', $formatter->getType());
    }
}
