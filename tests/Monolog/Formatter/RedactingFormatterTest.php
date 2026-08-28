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
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Test\MonologTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testRedactsSensitiveParameterValues()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%message% %context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(context: ['user' => new SensitiveParamHolder()]));

        $this->assertStringNotContainsString('s3cret-webhook', $output);
        $this->assertStringContainsString('[REDACTED]', $output);
        // the non-sensitive parameter is left alone
        $this->assertStringContainsString('user-1', $output);
    }

    public function testRedactsSensitiveParameterValuesWithJsonFormatter()
    {
        // JsonFormatter hands objects straight to json_encode, so nothing normalizes them
        $formatter = new RedactingFormatter(new JsonFormatter());

        $output = $formatter->format($this->getRecord(context: ['user' => new SensitiveParamHolder()]));

        $this->assertIsString($output);
        $this->assertStringNotContainsString('s3cret-webhook', $output);
        $this->assertStringContainsString('"mySecretUrl":"[REDACTED]"', $output);
    }

    public function testRedactsNonPublicSensitiveParameterLeakedByToString()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(context: ['conn' => new SensitiveDsnHolder()]));

        $this->assertStringNotContainsString('hunter2', $output);
        $this->assertStringContainsString('Connection([REDACTED])', $output);
    }

    public function testRedactsNonPromotedSensitiveParameterMatchedByPropertyName()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(context: ['sub' => new SensitiveNonPromotedHolder()]));

        $this->assertStringNotContainsString('tok-abcdefghij', $output);
        $this->assertStringContainsString('[REDACTED]', $output);
    }

    public function testRedactsSensitiveParameterOnNestedObjectAndInsideArrays()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(context: ['list' => [['nested' => new SensitiveNestingHolder()]]]));

        $this->assertStringNotContainsString('s3cret-webhook', $output);
        $this->assertStringContainsString('[REDACTED]', $output);
    }

    public function testRedactsSecretAlreadyInterpolatedIntoTheMessage()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%message% %context%', 'Y-m-d'));
        $processor = new PsrLogMessageProcessor();

        $output = $formatter->format($processor($this->getRecord(
            message: 'calling api with {token}',
            context: ['token' => 'tok-supersecret-value'],
        )));

        $this->assertStringNotContainsString('tok-supersecret-value', $output);
        $this->assertStringContainsString('calling api with [REDACTED]', $output);
    }

    public function testRedactsSecretEmbeddedInAnExceptionMessage()
    {
        $formatter = new RedactingFormatter(new JsonFormatter());

        $output = $formatter->format($this->getRecord(context: [
            'user' => new SensitiveParamHolder(),
            'exception' => new \RuntimeException('could not reach https://example.org/s3cret-webhook'),
        ]));

        $this->assertIsString($output);
        $this->assertStringNotContainsString('s3cret-webhook', $output);
        $this->assertStringContainsString('could not reach [REDACTED]', $output);
    }

    public function testRedactsSecretsNeedingJsonEscaping()
    {
        $formatter = new RedactingFormatter(new JsonFormatter(), sensitiveKeys: ['password']);

        $output = $formatter->format($this->getRecord(
            message: 'login failed for pa"ss\\word-123',
            context: ['password' => 'pa"ss\\word-123'],
        ));

        $this->assertIsString($output);
        $this->assertStringNotContainsString('word-123', $output);
        $this->assertSame(2, substr_count($output, '[REDACTED]')); // masked key + swept message
    }

    public function testRedactsSensitiveParameterValueObjects()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%message% %context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(
            message: 'token was tok-supersecret-value',
            context: ['arg' => new \SensitiveParameterValue('tok-supersecret-value')],
        ));

        $this->assertStringNotContainsString('tok-supersecret-value', $output);
    }

    public function testShortSecretsAreNotSwept()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%message% %context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(
            message: 'user pass logged in',
            context: ['password' => 'pass'],
        ));

        // the key is still masked, but "pass" is too short to be removed from the message
        $this->assertStringContainsString('user pass logged in', $output);
        $this->assertStringContainsString('[REDACTED]', $output);
    }

    public function testMinSecretLengthIsConfigurable()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%message%', 'Y-m-d'), minSecretLength: 4);

        $output = $formatter->format($this->getRecord(message: 'user pass logged in', context: ['password' => 'pass']));

        $this->assertStringContainsString('user [REDACTED] logged in', $output);
    }

    public function testSensitiveParameterSupportCanBeDisabled()
    {
        $formatter = new RedactingFormatter(new LineFormatter('%message% %context%', 'Y-m-d'), redactSensitiveParameters: false);

        $output = $formatter->format($this->getRecord(
            message: 'calling https://example.org/s3cret-webhook',
            context: ['user' => new SensitiveParamHolder(), 'token' => 'tok-supersecret-value'],
        ));

        $this->assertStringContainsString('calling https://example.org/s3cret-webhook', $output);
        // sensitive keys are still swept from the output, only the objects are left alone
        $this->assertStringNotContainsString('tok-supersecret-value', $output);
    }

    #[DataProvider('provideEdgeCaseObjects')]
    public function testHandlesObjectsWithoutUsableConstructors(object $object)
    {
        $formatter = new RedactingFormatter(new LineFormatter('%context%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(context: ['edge' => $object]));

        $this->assertIsString($output);
    }

    public static function provideEdgeCaseObjects(): array
    {
        $cyclic = new SensitiveCyclicHolder();
        $cyclic->next = $cyclic;

        return [
            'no constructor' => [new \stdClass()],
            'enum' => [SensitiveTestEnum::One],
            'anonymous class' => [new class ('anon-secret-value') {
                public function __construct(#[\SensitiveParameter] public string $secret)
                {
                }
            }],
            'uninitialized property' => [(new \ReflectionClass(SensitiveNonPromotedHolder::class))->newInstanceWithoutConstructor()],
            'cyclic graph' => [$cyclic],
        ];
    }

    public function testCyclicGraphSecretsAreStillFound()
    {
        $cyclic = new SensitiveCyclicHolder();
        $cyclic->next = $cyclic;

        $formatter = new RedactingFormatter(new LineFormatter('%message%', 'Y-m-d'));

        $output = $formatter->format($this->getRecord(message: 'leaked cyclic-secret-value', context: ['obj' => $cyclic]));

        $this->assertStringContainsString('leaked [REDACTED]', $output);
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

class SensitiveParamHolder
{
    public function __construct(
        public string $id = 'user-1',
        #[\SensitiveParameter]
        public string $mySecretUrl = 'https://example.org/s3cret-webhook',
    ) {
    }
}

class SensitiveDsnHolder
{
    public function __construct(
        #[\SensitiveParameter]
        private string $dsn = 'mysql://user:hunter2@localhost/db',
    ) {
    }

    public function __toString(): string
    {
        return 'Connection('.$this->dsn.')';
    }
}

class SensitiveNonPromotedHolder
{
    public string $token;

    public function __construct(#[\SensitiveParameter] string $token = 'tok-abcdefghij')
    {
        $this->token = $token;
    }
}

class SensitiveNestingHolder
{
    public function __construct(public SensitiveParamHolder $inner = new SensitiveParamHolder())
    {
    }
}

class SensitiveCyclicHolder
{
    public ?self $next = null;

    public function __construct(#[\SensitiveParameter] public string $secret = 'cyclic-secret-value')
    {
    }
}

enum SensitiveTestEnum: string
{
    case One = 'one';
}
