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
use Monolog\Formatter\GelfMessageFormatter;

class GelfMessageFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!class_exists("Gelf\Message")) {
            $this->markTestSkipped("mlehner/gelf-php not installed");
        }
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testDefaultFormatter()
    {
        $formatter = new GelfMessageFormatter();
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array(),
            'datetime' => new \DateTime("@0"),
            'extra' => array(),
            'message' => 'log',
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);
        $this->assertEquals(0, $message->getTimestamp());
        $this->assertEquals('log', $message->getShortMessage());
        $this->assertEquals('meh', $message->getFacility());
        $this->assertEquals(null, $message->getLine());
        $this->assertEquals(null, $message->getFile());
        $this->assertEquals(LOG_ERR, $message->getLevel());
        $this->assertNotEmpty($message->getHost());

        $formatter = new GelfMessageFormatter('mysystem');

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);
        $this->assertEquals('mysystem', $message->getHost());
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithFileAndLine()
    {
        $formatter = new GelfMessageFormatter();
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('file' => 'test', 'line' => 14),
            'message' => 'log',
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);
        $this->assertEquals('test', $message->getFile());
        $this->assertEquals(14, $message->getLine());
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithContext()
    {
        $formatter = new GelfMessageFormatter();
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('key' => 'pair'),
            'message' => 'log'
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);

        $message_array = $message->toArray();

        $this->assertArrayHasKey('_ctxt_from', $message_array);
        $this->assertEquals('logger', $message_array['_ctxt_from']);

        // Test with extraPrefix
        $formatter = new GelfMessageFormatter(null, null, 'CTX');
        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);

        $message_array = $message->toArray();

        $this->assertArrayHasKey('_CTXfrom', $message_array);
        $this->assertEquals('logger', $message_array['_CTXfrom']);

    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithExtra()
    {
        $formatter = new GelfMessageFormatter();
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('key' => 'pair'),
            'message' => 'log'
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);

        $message_array = $message->toArray();

        $this->assertArrayHasKey('_key', $message_array);
        $this->assertEquals('pair', $message_array['_key']);

        // Test with extraPrefix
        $formatter = new GelfMessageFormatter(null, 'EXT');
        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);

        $message_array = $message->toArray();

        $this->assertArrayHasKey('_EXTkey', $message_array);
        $this->assertEquals('pair', $message_array['_EXTkey']);
    }
}
