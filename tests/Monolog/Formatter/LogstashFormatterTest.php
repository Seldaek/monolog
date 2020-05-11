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
use PHPUnit\Framework\TestCase;

class LogstashFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testDefaultFormatterV1()
    {
        $formatter = new LogstashFormatter('test', 'hostname');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals("1970-01-01T00:00:00.000000+00:00", $message['@timestamp']);
        $this->assertEquals("1", $message['@version']);
        $this->assertEquals('log', $message['message']);
        $this->assertEquals('meh', $message['channel']);
        $this->assertEquals('ERROR', $message['level']);
        $this->assertEquals(Logger::ERROR, $message['monolog_level']);
        $this->assertEquals('test', $message['type']);
        $this->assertEquals('hostname', $message['host']);

        $formatter = new LogstashFormatter('mysystem');

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals('mysystem', $message['type']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithFileAndLineV1()
    {
        $formatter = new LogstashFormatter('test');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger'],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => ['file' => 'test', 'line' => 14],
            'message' => 'log',
        ];

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals('test', $message['extra']['file']);
        $this->assertEquals(14, $message['extra']['line']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithContextV1()
    {
        $formatter = new LogstashFormatter('test');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger'],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => ['key' => 'pair'],
            'message' => 'log',
        ];

        $message = json_decode($formatter->format($record), true);

        $this->assertArrayHasKey('context', $message);
        $this->assertArrayHasKey('from', $message['context']);
        $this->assertEquals('logger', $message['context']['from']);

        // Test with extraPrefix
        $formatter = new LogstashFormatter('test', null, 'extra', 'CTX');
        $message = json_decode($formatter->format($record), true);

        $this->assertArrayHasKey('CTX', $message);
        $this->assertArrayHasKey('from', $message['CTX']);
        $this->assertEquals('logger', $message['CTX']['from']);
    }

    /**
     * @covers Monolog\Formatter\LogstashFormatter::format
     */
    public function testFormatWithExtraV1()
    {
        $formatter = new LogstashFormatter('test');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger'],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => ['key' => 'pair'],
            'message' => 'log',
        ];

        $message = json_decode($formatter->format($record), true);

        $this->assertArrayHasKey('extra', $message);
        $this->assertArrayHasKey('key', $message['extra']);
        $this->assertEquals('pair', $message['extra']['key']);

        // Test with extraPrefix
        $formatter = new LogstashFormatter('test', null, 'EXTRA');
        $message = json_decode($formatter->format($record), true);

        $this->assertArrayHasKey('EXTRA', $message);
        $this->assertArrayHasKey('key', $message['EXTRA']);
        $this->assertEquals('pair', $message['EXTRA']['key']);
    }

    public function testFormatWithApplicationNameV1()
    {
        $formatter = new LogstashFormatter('app', 'test');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger'],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => ['key' => 'pair'],
            'message' => 'log',
        ];

        $message = json_decode($formatter->format($record), true);

        $this->assertArrayHasKey('type', $message);
        $this->assertEquals('app', $message['type']);
    }

    public function testFormatWithLatin9Data()
    {
        $formatter = new LogstashFormatter('test', 'hostname');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => '¯\_(ツ)_/¯',
            'context' => [],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [
                'user_agent' => "\xD6WN; FBCR/OrangeEspa\xF1a; Vers\xE3o/4.0; F\xE4rist",
            ],
            'message' => 'log',
        ];

        $message = json_decode($formatter->format($record), true);

        $this->assertEquals("1970-01-01T00:00:00.000000+00:00", $message['@timestamp']);
        $this->assertEquals('log', $message['message']);
        $this->assertEquals('¯\_(ツ)_/¯', $message['channel']);
        $this->assertEquals('ERROR', $message['level']);
        $this->assertEquals('test', $message['type']);
        $this->assertEquals('hostname', $message['host']);
        $this->assertEquals('�WN; FBCR/OrangeEspa�a; Vers�o/4.0; F�rist', $message['extra']['user_agent']);
    }
}
