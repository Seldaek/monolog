<?php
declare(strict_types=1);

namespace Monolog\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RedactSensitiveProcessorTest extends TestCase
{
    public function testRedactsContextAndExtraKeys(): void
    {
        $p = new RedactSensitiveProcessor();

        $rec = new LogRecord(
            datetime: new \DateTimeImmutable('@0'),
            channel: 'test',
            level: Level::Info,
            message: 'Login for {user}',
            context: ['user' => 'rishi', 'password' => 'super-secret', 'nested' => ['api_key' => 'abc123']],
            extra: ['token' => 't-456', 'irrelevant' => 'keep']
        );

        $out = $p($rec);

        $this->assertSame('REDACTED', $out->context['password']);
        $this->assertSame('REDACTED', $out->context['nested']['api_key']);
        $this->assertSame('REDACTED', $out->extra['token']);
        $this->assertSame('keep', $out->extra['irrelevant']);
    }

    public function testRedactsWithPatterns(): void
    {
        $p = new RedactSensitiveProcessor(
            sensitiveKeys: [],
            patterns: ['/(Bearer\\s+)[A-Za-z0-9\\._-]+/i', '/([\\w.%+-]+@[\\w.-]+\\.[A-Za-z]{2,})/']
        );

        $rec = new LogRecord(
            new \DateTimeImmutable('@0'), 'test', Level::Info,
            'Auth {h}: Bearer abc.def-ghi and user john@example.com',
            ['h' => 'header'], []
        );

        $out = $p($rec);
        $this->assertSame('Auth {h}: REDACTED and user REDACTED', $out->message);
    }

    public function testIgnoresInvalidRegexSafely(): void
    {
        $p = new RedactSensitiveProcessor([], ['/[invalid/']);
        $rec = new LogRecord(new \DateTimeImmutable('@0'), 'test', Level::Info, 'hello', [], []);
        $out = $p($rec);
        $this->assertSame('hello', $out->message);
    }
}
?>