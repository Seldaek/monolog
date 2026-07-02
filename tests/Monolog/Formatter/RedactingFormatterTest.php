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
use Monolog\LogRecord;
use Monolog\Test\MonologTestCase;

/**
 * @covers Monolog\Formatter\RedactingFormatter
 */
class RedactingFormatterTest extends MonologTestCase
{
    public function testRedactsSensitiveKeysInContextAndExtra()
    {
        $captured = null;
        $formatter = new RedactingFormatter($this->capturingFormatter($captured));

        $formatter->format($this->getRecord(
            context: ['user' => 'rishi', 'password' => 'super-secret', 'nested' => ['api_key' => 'abc123']],
            extra: ['token' => 't-456', 'irrelevant' => 'keep'],
        ));

        $this->assertSame('rishi', $captured->context['user']);
        $this->assertSame('[REDACTED]', $captured->context['password']);
        $this->assertSame('[REDACTED]', $captured->context['nested']['api_key']);
        $this->assertSame('[REDACTED]', $captured->extra['token']);
        $this->assertSame('keep', $captured->extra['irrelevant']);
    }

    public function testKeyMatchingIsCaseInsensitive()
    {
        $captured = null;
        $formatter = new RedactingFormatter($this->capturingFormatter($captured));

        $formatter->format($this->getRecord(context: ['Password' => 'x', 'AUTHORIZATION' => 'Bearer y']));

        $this->assertSame('[REDACTED]', $captured->context['Password']);
        $this->assertSame('[REDACTED]', $captured->context['AUTHORIZATION']);
    }

    public function testRedactsPatternsInFinalOutput()
    {
        $formatter = new RedactingFormatter(
            new LineFormatter('%message% %context%', 'Y-m-d'),
            sensitiveKeys: [],
            patterns: ['{Bearer\s+[A-Za-z0-9._-]+}'],
        );

        $output = $formatter->format($this->getRecord(
            message: 'Auth header Bearer abc.def-ghi',
            context: ['header' => 'Bearer zzz999'],
        ));

        $this->assertStringContainsString('Auth header [REDACTED]', $output);
        $this->assertStringNotContainsString('Bearer', $output);
    }

    public function testTokenPatternConstantRedactsLongTokens()
    {
        $formatter = new RedactingFormatter(
            new LineFormatter('%message%', 'Y-m-d'),
            sensitiveKeys: [],
            patterns: [RedactingFormatter::TOKEN_PATTERN],
        );

        $output = $formatter->format($this->getRecord(message: 'token is test_0123456789abcdefghijklmnopqrstuvwxyz here'));

        $this->assertStringContainsString('token is [REDACTED] here', $output);
    }

    public function testSweepsSecretsHiddenInsideNormalizedObjects()
    {
        // The secret lives inside an exception that only becomes a string once
        // the wrapped JsonFormatter normalizes the record. Redacting the record
        // alone would miss it; running the patterns over the output catches it.
        $formatter = new RedactingFormatter(
            new JsonFormatter(),
            sensitiveKeys: [],
            patterns: ['{Bearer\s+[A-Za-z0-9._-]+}'],
        );

        $output = $formatter->format($this->getRecord(
            context: ['exception' => new \RuntimeException('failed using Bearer abc.def-ghi')],
        ));

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Bearer abc.def-ghi', $output);
        $this->assertStringContainsString('[REDACTED]', $output);
    }

    public function testSweepsArrayReturningFormatters()
    {
        $formatter = new RedactingFormatter(
            new NormalizerFormatter('Y-m-d'),
            sensitiveKeys: [],
            patterns: ['{Bearer\s+[A-Za-z0-9._-]+}'],
        );

        $output = $formatter->format($this->getRecord(message: 'Bearer abc.def-ghi'));

        $this->assertIsArray($output);
        $this->assertSame('[REDACTED]', $output['message']);
    }

    public function testFormatBatchRedactsEachRecord()
    {
        $formatter = new RedactingFormatter(
            new LineFormatter('%message% %context%', 'Y-m-d'),
            patterns: ['{Bearer\s+[A-Za-z0-9._-]+}'],
        );

        // LineFormatter::formatBatch joins records into a single string
        $output = $formatter->formatBatch([
            $this->getRecord(message: 'one Bearer aaa.bbb-ccc', context: ['password' => 'p1']),
            $this->getRecord(message: 'two', context: ['token' => 't2']),
        ]);

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Bearer', $output);
        $this->assertStringNotContainsString('p1', $output);
        $this->assertStringNotContainsString('t2', $output);
        $this->assertSame(3, substr_count($output, '[REDACTED]')); // password key, token key, and Bearer pattern
    }

    public function testFormatBatchWithArrayReturningFormatter()
    {
        $formatter = new RedactingFormatter(new NormalizerFormatter('Y-m-d'));

        $output = $formatter->formatBatch([
            $this->getRecord(context: ['password' => 'p1']),
            $this->getRecord(context: ['token' => 't2']),
        ]);

        $this->assertIsArray($output);
        $this->assertSame('[REDACTED]', $output[0]['context']['password']);
        $this->assertSame('[REDACTED]', $output[1]['context']['token']);
    }

    public function testThrowsOnInvalidPattern()
    {
        $this->expectException(\InvalidArgumentException::class);

        new RedactingFormatter(new LineFormatter(), patterns: ['{invalid']);
    }

    public function testNoPatternsLeavesOutputUntouched()
    {
        $inner = new LineFormatter('%message%', 'Y-m-d');
        $record = $this->getRecord(message: 'a long token test_0123456789abcdefghijklmnopqrstuvwxyz');

        $formatter = new RedactingFormatter($inner, sensitiveKeys: []);

        $this->assertSame($inner->format($record), $formatter->format($record));
    }

    public function testCustomMask()
    {
        $captured = null;
        $formatter = new RedactingFormatter($this->capturingFormatter($captured), mask: '***');

        $formatter->format($this->getRecord(context: ['password' => 'x']));

        $this->assertSame('***', $captured->context['password']);
    }

    /**
     * Returns a formatter that records the (already redacted) LogRecord it receives
     * into $captured, so tests can assert on the structured record passed downstream.
     */
    private function capturingFormatter(?LogRecord &$captured): FormatterInterface
    {
        return new class($captured) implements FormatterInterface {
            public function __construct(private ?LogRecord &$captured)
            {
            }

            public function format(LogRecord $record)
            {
                $this->captured = $record;

                return '';
            }

            public function formatBatch(array $records)
            {
                foreach ($records as $record) {
                    $this->captured = $record;
                }

                return '';
            }
        };
    }
}
