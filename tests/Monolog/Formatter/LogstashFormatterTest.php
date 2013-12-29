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

class LogstashFormatterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testDefaultFormatter()
    {
        $formatter = new LogstashFormatter('test', 'hostname');
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array(),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array(),
            'message'    => 'log',
        );

        $message = json_decode($formatter->format($record), true);

        self::assertEquals("1970-01-01T00:00:00.000000+00:00", $message['@timestamp']);
        self::assertEquals('log', $message['@message']);
        self::assertEquals('meh', $message['@fields']['channel']);
        self::assertContains('meh', $message['@tags']);
        self::assertEquals(Logger::ERROR, $message['@fields']['level']);
        self::assertEquals('test', $message['@type']);
        self::assertEquals('hostname', $message['@source']);

        $formatter = new LogstashFormatter('mysystem');

        $message = json_decode($formatter->format($record), true);

        self::assertEquals('mysystem', $message['@type']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithFileAndLine()
    {
        $formatter = new LogstashFormatter('test');
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('file' => 'test', 'line' => 14),
            'message'    => 'log',
        );

        $message = json_decode($formatter->format($record), true);

        self::assertEquals('test', $message['@fields']['file']);
        self::assertEquals(14, $message['@fields']['line']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithContext()
    {
        $formatter = new LogstashFormatter('test');
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        $messageArray = $message['@fields'];

        self::assertArrayHasKey('ctxt_from', $messageArray);
        self::assertEquals('logger', $messageArray['ctxt_from']);

        // Test with extraPrefix
        $formatter = new LogstashFormatter('test', null, null, 'CTX');
        $message   = json_decode($formatter->format($record), true);

        $messageArray = $message['@fields'];

        self::assertArrayHasKey('CTXfrom', $messageArray);
        self::assertEquals('logger', $messageArray['CTXfrom']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithExtra()
    {
        $formatter = new LogstashFormatter('test');
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        $messageArray = $message['@fields'];

        self::assertArrayHasKey('key', $messageArray);
        self::assertEquals('pair', $messageArray['key']);

        // Test with extraPrefix
        $formatter = new LogstashFormatter('test', null, 'EXT');
        $message   = json_decode($formatter->format($record), true);

        $messageArray = $message['@fields'];

        self::assertArrayHasKey('EXTkey', $messageArray);
        self::assertEquals('pair', $messageArray['EXTkey']);
    }

    public function testFormatWithApplicationName()
    {
        $formatter = new LogstashFormatter('app', 'test');
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        self::assertArrayHasKey('@type', $message);
        self::assertEquals('app', $message['@type']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testDefaultFormatterV1()
    {
        $formatter = new LogstashFormatter('test', 'hostname', null, 'ctxt_', LogstashFormatter::V1);
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array(),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array(),
            'message'    => 'log',
        );

        $message = json_decode($formatter->format($record), true);

        self::assertEquals("1970-01-01T00:00:00.000000+00:00", $message['@timestamp']);
        self::assertEquals("1", $message['@version']);
        self::assertEquals('log', $message['message']);
        self::assertEquals('meh', $message['channel']);
        self::assertEquals('ERROR', $message['level']);
        self::assertEquals('test', $message['type']);
        self::assertEquals('hostname', $message['host']);

        $formatter = new LogstashFormatter('mysystem', null, null, 'ctxt_', LogstashFormatter::V1);

        $message = json_decode($formatter->format($record), true);

        self::assertEquals('mysystem', $message['type']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithFileAndLineV1()
    {
        $formatter = new LogstashFormatter('test', null, null, 'ctxt_', LogstashFormatter::V1);
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('file' => 'test', 'line' => 14),
            'message'    => 'log',
        );

        $message = json_decode($formatter->format($record), true);

        self::assertEquals('test', $message['file']);
        self::assertEquals(14, $message['line']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithContextV1()
    {
        $formatter = new LogstashFormatter('test', null, null, 'ctxt_', LogstashFormatter::V1);
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        self::assertArrayHasKey('ctxt_from', $message);
        self::assertEquals('logger', $message['ctxt_from']);

        // Test with extraPrefix
        $formatter = new LogstashFormatter('test', null, null, 'CTX', LogstashFormatter::V1);
        $message   = json_decode($formatter->format($record), true);

        self::assertArrayHasKey('CTXfrom', $message);
        self::assertEquals('logger', $message['CTXfrom']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithExtraV1()
    {
        $formatter = new LogstashFormatter('test', null, null, 'ctxt_', LogstashFormatter::V1);
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        self::assertArrayHasKey('key', $message);
        self::assertEquals('pair', $message['key']);

        // Test with extraPrefix
        $formatter = new LogstashFormatter('test', null, 'EXT', 'ctxt_', LogstashFormatter::V1);
        $message   = json_decode($formatter->format($record), true);

        self::assertArrayHasKey('EXTkey', $message);
        self::assertEquals('pair', $message['EXTkey']);
    }

    public function testFormatWithApplicationNameV1()
    {
        $formatter = new LogstashFormatter('app', 'test', null, 'ctxt_', LogstashFormatter::V1);
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = json_decode($formatter->format($record), true);

        self::assertArrayHasKey('type', $message);
        self::assertEquals('app', $message['type']);
    }
}
