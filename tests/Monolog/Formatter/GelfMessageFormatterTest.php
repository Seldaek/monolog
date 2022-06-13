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

use Monolog\Level;
use Monolog\Test\TestCase;

class GelfMessageFormatterTest extends TestCase
{
    public function setUp(): void
    {
        if (!class_exists('\Gelf\Message')) {
            $this->markTestSkipped("graylog2/gelf-php is not installed");
        }
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testDefaultFormatter()
    {
        $formatter = new GelfMessageFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            datetime: new \DateTimeImmutable("@0"),
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);
        $this->assertEquals(0, $message->getTimestamp());
        $this->assertEquals('log', $message->getShortMessage());
        $this->assertEquals('meh', $message->getAdditional('facility'));
        $this->assertEquals(false, $message->hasAdditional('line'));
        $this->assertEquals(false, $message->hasAdditional('file'));
        $this->assertEquals($this->isLegacy() ? 3 : 'error', $message->getLevel());
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
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['file' => 'test', 'line' => 14],
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);
        $this->assertEquals('test', $message->getAdditional('file'));
        $this->assertEquals(14, $message->getAdditional('line'));
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithContext()
    {
        $formatter = new GelfMessageFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['key' => 'pair'],
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
    public function testFormatWithContextContainingException()
    {
        $formatter = new GelfMessageFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger', 'exception' => [
                'class' => '\Exception',
                'file'  => '/some/file/in/dir.php:56',
                'trace' => ['/some/file/1.php:23', '/some/file/2.php:3'],
            ]],
            datetime: new \DateTimeImmutable("@0"),
        );

        $message = $formatter->format($record);

        $this->assertInstanceOf('Gelf\Message', $message);

        $this->assertEquals("/some/file/in/dir.php", $message->getAdditional('file'));
        $this->assertEquals("56", $message->getAdditional('line'));
    }

    /**
     * @covers Monolog\Formatter\GelfMessageFormatter::format
     */
    public function testFormatWithExtra()
    {
        $formatter = new GelfMessageFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['from' => 'logger'],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['key' => 'pair'],
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

    public function testFormatWithLargeData()
    {
        $formatter = new GelfMessageFormatter();
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['exception' => str_repeat(' ', 32767)],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['key' => str_repeat(' ', 32767)],
        );
        $message = $formatter->format($record);
        $messageArray = $message->toArray();

        // 200 for padding + metadata
        $length = 200;

        foreach ($messageArray as $key => $value) {
            if (!in_array($key, ['level', 'timestamp']) && is_string($value)) {
                $length += strlen($value);
            }
        }

        $this->assertLessThanOrEqual(65792, $length, 'The message length is no longer than the maximum allowed length');
    }

    public function testFormatWithUnlimitedLength()
    {
        $formatter = new GelfMessageFormatter('LONG_SYSTEM_NAME', null, 'ctxt_', PHP_INT_MAX);
        $record = $this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['exception' => str_repeat(' ', 32767 * 2)],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['key' => str_repeat(' ', 32767 * 2)],
        );
        $message = $formatter->format($record);
        $messageArray = $message->toArray();

        // 200 for padding + metadata
        $length = 200;

        foreach ($messageArray as $key => $value) {
            if (!in_array($key, ['level', 'timestamp'])) {
                $length += strlen($value);
            }
        }

        $this->assertGreaterThanOrEqual(131289, $length, 'The message should not be truncated');
    }

    public function testFormatWithLargeCyrillicData()
    {
        $formatter = new GelfMessageFormatter();
        $record = $this->getRecord(
            Level::Error,
            str_repeat('в', 32767),
            channel: 'meh',
            context: ['exception' => str_repeat('а', 32767)],
            datetime: new \DateTimeImmutable("@0"),
            extra: ['key' => str_repeat('б', 32767)],
        );
        $message = $formatter->format($record);
        $messageArray = $message->toArray();

        $messageString = json_encode($messageArray);

        $this->assertIsString($messageString);
    }

    private function isLegacy()
    {
        return interface_exists('\Gelf\IMessagePublisher');
    }
}
