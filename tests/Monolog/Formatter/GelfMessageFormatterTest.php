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
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array(),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array(),
            'message'    => 'log',
        );

        $message = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);
        self::assertEquals(0, $message->getTimestamp());
        self::assertEquals('log', $message->getShortMessage());
        self::assertEquals('meh', $message->getFacility());
        self::assertEquals(null, $message->getLine());
        self::assertEquals(null, $message->getFile());
        self::assertEquals(3, $message->getLevel());
        self::assertNotEmpty($message->getHost());

        $formatter = new GelfMessageFormatter('mysystem');

        $message = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);
        self::assertEquals('mysystem', $message->getHost());
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithFileAndLine()
    {
        $formatter = new GelfMessageFormatter();
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('file' => 'test', 'line' => 14),
            'message'    => 'log',
        );

        $message = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);
        self::assertEquals('test', $message->getFile());
        self::assertEquals(14, $message->getLine());
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithContext()
    {
        $formatter = new GelfMessageFormatter();
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);

        $messageArray = $message->toArray();

        self::assertArrayHasKey('_ctxt_from', $messageArray);
        self::assertEquals('logger', $messageArray['_ctxt_from']);

        // Test with extraPrefix
        $formatter = new GelfMessageFormatter(null, null, 'CTX');
        $message   = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);

        $messageArray = $message->toArray();

        self::assertArrayHasKey('_CTXfrom', $messageArray);
        self::assertEquals('logger', $messageArray['_CTXfrom']);
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithContextContainingException()
    {
        $formatter = new GelfMessageFormatter();
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger', 'exception' => array(
                'class' => '\Exception',
                'file'  => '/some/file/in/dir.php:56',
                'trace' => array('/some/file/1.php:23', '/some/file/2.php:3')
            )),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array(),
            'message'    => 'log'
        );

        $message = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);

        self::assertEquals("/some/file/in/dir.php", $message->getFile());
        self::assertEquals("56", $message->getLine());
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithExtra()
    {
        $formatter = new GelfMessageFormatter();
        $record    = array(
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'meh',
            'context'    => array('from' => 'logger'),
            'datetime'   => new \DateTime("@0"),
            'extra'      => array('key' => 'pair'),
            'message'    => 'log'
        );

        $message = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);

        $messageArray = $message->toArray();

        self::assertArrayHasKey('_key', $messageArray);
        self::assertEquals('pair', $messageArray['_key']);

        // Test with extraPrefix
        $formatter = new GelfMessageFormatter(null, 'EXT');
        $message   = $formatter->format($record);

        self::assertInstanceOf('Gelf\Message', $message);

        $messageArray = $message->toArray();

        self::assertArrayHasKey('_EXTkey', $messageArray);
        self::assertEquals('pair', $messageArray['_EXTkey']);
    }
}
