<?php

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
use Monolog\Formatter\LogstashEventFormatter;

class LogstashEventFormatterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers Monolog\Formatter\LogstashEventFormatter::format
     */
    public function testDefaultFormatter()
    {
        $formatter = new LogstashEventFormatter('test');
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array(),
            'datetime' => new \DateTime("@0"),
            'extra' => array(),
            'message' => 'log',
        );

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals("1970-01-01T00:00:00+00:00", $message['@timestamp']);
        $this->assertEquals('log', $message['@message']);
        $this->assertEquals('meh', $message['@fields']['channel']);
        $this->assertContains('meh', $message['@tags']);
        $this->assertEquals(Logger::ERROR, $message['@fields']['level']);
        $this->assertEquals('test', $message['@source']);

        $formatter = new LogstashEventFormatter('mysystem');

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals('mysystem', $message['@source']);
    }

    /**
     * @covers Monolog\Formatter\LogstashEventFormatter::format
     */
    public function testFormatWithFileAndLine()
    {
        $formatter = new LogstashEventFormatter('test');
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('file' => 'test', 'line' => 14),
            'message' => 'log',
        );

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals('test', $message['@fields']['file']);
        $this->assertEquals(14, $message['@fields']['line']);
    }

    /**
     * @covers Monolog\Formatter\LogstashEventFormatter::format
     */
    public function testFormatWithContext()
    {
        $formatter = new LogstashEventFormatter('test');
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('key' => 'pair'),
            'message' => 'log'
        );

        $message = json_decode($formatter->format($record), true);


        $message_array = $message['@fields'];

        $this->assertArrayHasKey('ctxt_from', $message_array);
        $this->assertEquals('logger', $message_array['ctxt_from']);

        // Test with extraPrefix
        $formatter = new LogstashEventFormatter('test', null, 'CTX');
        $message = json_decode($formatter->format($record), true);


        $message_array = $message['@fields'];

        $this->assertArrayHasKey('CTXfrom', $message_array);
        $this->assertEquals('logger', $message_array['CTXfrom']);

    }

    /**
     * @covers Monolog\Formatter\LogstashEventFormatter::format
     */
    public function testFormatWithExtra()
    {
        $formatter = new LogstashEventFormatter('test');
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('key' => 'pair'),
            'message' => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        $message_array = $message['@fields'];

        $this->assertArrayHasKey('key', $message_array);
        $this->assertEquals('pair', $message_array['key']);

        // Test with extraPrefix
        $formatter = new LogstashEventFormatter('test', 'EXT');
        $message = json_decode($formatter->format($record), true);

        $message_array = $message['@fields'];

        $this->assertArrayHasKey('EXTkey', $message_array);
        $this->assertEquals('pair', $message_array['EXTkey']);
    }
}
