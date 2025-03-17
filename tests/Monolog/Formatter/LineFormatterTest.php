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

use Monolog\Test\MonologTestCase;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * @covers Monolog\Formatter\LineFormatter
 */
class LineFormatterTest extends MonologTestCase
{
    public function testDefFormatWithString()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Warning,
            'foo',
            channel: 'log',
        ));
        $this->assertEquals('['.date('Y-m-d').'] log.WARNING: foo [] []'."\n", $message);
    }

    public function testDefFormatWithArrayContext()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Error,
            'foo',
            channel: 'meh',
            context: [
                'foo' => 'bar',
                'baz' => 'qux',
                'bool' => false,
                'null' => null,
            ],
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: foo {"foo":"bar","baz":"qux","bool":false,"null":null} []'."\n", $message);
    }

    public function testDefFormatExtras()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            extra: ['ip' => '127.0.0.1'],
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log [] {"ip":"127.0.0.1"}'."\n", $message);
    }

    public function testFormatExtras()
    {
        $formatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra.file% %extra%\n", 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            extra: ['ip' => '127.0.0.1', 'file' => 'test'],
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log [] test {"ip":"127.0.0.1"}'."\n", $message);
    }

    public function testContextAndExtraOptionallyNotShownIfEmpty()
    {
        $formatter = new LineFormatter(null, 'Y-m-d', false, true);
        $message = $formatter->format($this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log  '."\n", $message);
    }

    public function testContextAndExtraReplacement()
    {
        $formatter = new LineFormatter('%context.foo% => %extra.foo%');
        $message = $formatter->format($this->getRecord(
            Level::Error,
            'log',
            channel: 'meh',
            context: ['foo' => 'bar'],
            extra: ['foo' => 'xbar'],
        ));

        $this->assertEquals('bar => xbar', $message);
    }

    public function testDefFormatWithObject()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Error,
            'foobar',
            channel: 'meh',
            context: [],
            extra: ['foo' => new TestFoo, 'bar' => new TestBar, 'baz' => [], 'res' => fopen('php://memory', 'rb')],
        ));

        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: foobar [] {"foo":{"Monolog\\\\Formatter\\\\TestFoo":{"foo":"fooValue"}},"bar":{"Monolog\\\\Formatter\\\\TestBar":"bar"},"baz":[],"res":"[resource(stream)]"}'."\n", $message);
    }

    public function testDefFormatWithException()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => new \RuntimeException('Foo')],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (RuntimeException(code: 0): Foo at '.substr($path, 1, -1).':'.(__LINE__ - 5).')"} []'."\n", $message);
    }

    public function testDefFormatWithExceptionAndStacktrace()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces();
        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => new \RuntimeException('Foo')],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertMatchesRegularExpression('{^\['.date('Y-m-d').'] core\.CRITICAL: foobar \{"exception":"\[object] \(RuntimeException\(code: 0\): Foo at '.preg_quote(substr($path, 1, -1)).':'.(__LINE__ - 5).'\)\n\[stacktrace]\n#0}', $message);
    }

    public function testInlineLineBreaksRespectsEscapedBackslashes()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->allowInlineLineBreaks();

        self::assertSame('{"test":"foo'."\n".'bar\\\\name-with-n"}', $formatter->stringify(["test" => "foo\nbar\\name-with-n"]));
        self::assertSame('["indexed'."\n".'arrays'."\n".'without'."\n".'key","foo'."\n".'bar\\\\name-with-n"]', $formatter->stringify(["indexed\narrays\nwithout\nkey", "foo\nbar\\name-with-n"]));
        self::assertSame('[{"first":"multi-dimensional'."\n".'arrays"},{"second":"foo'."\n".'bar\\\\name-with-n"}]', $formatter->stringify([["first" => "multi-dimensional\narrays"], ["second" => "foo\nbar\\name-with-n"]]));
    }

    public function testDefFormatWithExceptionAndStacktraceParserFull()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces(true, function ($line) {
            return $line;
        });

        $message = $formatter->format($this->getRecord(Level::Critical, context: ['exception' => new \RuntimeException('Foo')]));

        $trace = explode('[stacktrace]', $message, 2)[1];

        $this->assertStringContainsString('TestSuite.php', $trace);
        $this->assertStringContainsString('TestRunner.php', $trace);
    }

    public function testDefFormatWithExceptionAndStacktraceParserCustom()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces(true, function ($line) {
            if (strpos($line, 'TestSuite.php') === false) {
                return $line;
            }
        });

        $message = $formatter->format($this->getRecord(Level::Critical, context: ['exception' => new \RuntimeException('Foo')]));

        $trace = explode('[stacktrace]', $message, 2)[1];

        $this->assertStringNotContainsString('TestSuite.php', $trace);
        $this->assertStringContainsString('TestRunner.php', $trace);
    }

    public function testDefFormatWithExceptionAndStacktraceParserEmpty()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces(true, function ($line) {
            return null;
        });

        $message = $formatter->format($this->getRecord(Level::Critical, context: ['exception' => new \RuntimeException('Foo')]));
        $trace = explode('[stacktrace]', $message, 2)[1];
        $this->assertStringNotContainsString('#', $trace);
        $this->assertSame(PHP_EOL . PHP_EOL . '"} []' . PHP_EOL, $trace);
    }

    public function testDefFormatWithPreviousException()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $previous = new \LogicException('Wut?');
        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => new \RuntimeException('Foo', 0, $previous)],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (RuntimeException(code: 0): Foo at '.substr($path, 1, -1).':'.(__LINE__ - 5).')\n[previous exception] [object] (LogicException(code: 0): Wut? at '.substr($path, 1, -1).':'.(__LINE__ - 10).')"} []'."\n", $message);
    }

    public function testDefFormatWithSoapFaultException()
    {
        if (!class_exists('SoapFault')) {
            $this->markTestSkipped('Requires the soap extension');
        }

        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => new \SoapFault('foo', 'bar', 'hello', 'world')],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (SoapFault(code: 0 faultcode: foo faultactor: hello detail: world): bar at '.substr($path, 1, -1).':'.(__LINE__ - 5).')"} []'."\n", $message);

        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => new \SoapFault('foo', 'bar', 'hello', (object) ['bar' => (object) ['biz' => 'baz'], 'foo' => 'world'])],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (SoapFault(code: 0 faultcode: foo faultactor: hello detail: {\"bar\":{\"biz\":\"baz\"},\"foo\":\"world\"}): bar at '.substr($path, 1, -1).':'.(__LINE__ - 5).')"} []'."\n", $message);
    }

    public function testBatchFormat()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->formatBatch([
            $this->getRecord(
                Level::Critical,
                'bar',
                channel: 'test',
            ),
            $this->getRecord(
                Level::Warning,
                'foo',
                channel: 'log',
            ),
        ]);
        $this->assertEquals('['.date('Y-m-d').'] test.CRITICAL: bar [] []'."\n".'['.date('Y-m-d').'] log.WARNING: foo [] []'."\n", $message);
    }

    public function testFormatShouldStripInlineLineBreaks()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(message: "foo\nbar"));

        $this->assertMatchesRegularExpression('/foo bar/', $message);
    }

    public function testFormatShouldNotStripInlineLineBreaksWhenFlagIsSet()
    {
        $formatter = new LineFormatter(null, 'Y-m-d', true);
        $message = $formatter->format($this->getRecord(message: "foo\nbar"));

        $this->assertMatchesRegularExpression('/foo\nbar/', $message);
    }

    public function testIndentStackTraces(): void
    {
        $formatter = new LineFormatter();
        $formatter->includeStacktraces();
        //$formatter->allowInlineLineBreaks();
        $formatter->indentStackTraces('    ');
        $message = $formatter->format($this->getRecord(message: "foo", context: ['exception' => new RuntimeException('lala')]));

        $this->assertStringContainsString('    [stacktrace]', $message);
        $this->assertStringContainsString('    #0', $message);
        $this->assertStringContainsString('    #1', $message);
    }

    public function testBasePath(): void
    {
        $formatter = new LineFormatter();
        $formatter->includeStacktraces();
        $formatter->setBasePath(\dirname(\dirname(\dirname(__DIR__))));
        $formatter->indentStackTraces('    ');
        $message = $formatter->format($this->getRecord(message: "foo", context: ['exception' => new RuntimeException('lala')]));

        $this->assertStringContainsString('    [stacktrace]', $message);
        $this->assertStringContainsString('    #0 vendor/phpunit/phpunit/src/Framework/TestCase.php', $message);
        $this->assertStringContainsString('    #1 vendor/phpunit/phpunit/', $message);
    }

    #[DataProvider('providerMaxLevelNameLength')]
    public function testMaxLevelNameLength(?int $maxLength, Level $logLevel, string $expectedLevelName): void
    {
        $formatter = new LineFormatter();
        $formatter->setMaxLevelNameLength($maxLength);
        $message = $formatter->format($this->getRecord(message: "foo\nbar", level: $logLevel));

        $this->assertStringContainsString("test.$expectedLevelName:", $message);
    }

    public static function providerMaxLevelNameLength(): array
    {
        return [
            'info_no_max_length' => [
                'maxLength' => null,
                'logLevel' => Level::Info,
                'expectedLevelName' => 'INFO',
            ],

            'error_max_length_3' => [
                'maxLength' => 3,
                'logLevel' => Level::Error,
                'expectedLevelName' => 'ERR',
            ],

            'debug_max_length_2' => [
                'maxLength' => 2,
                'logLevel' => Level::Debug,
                'expectedLevelName' => 'DE',
            ],
        ];
    }
}

class TestFoo
{
    public string $foo = 'fooValue';
}

class TestBar
{
    public function __toString()
    {
        return 'bar';
    }
}
