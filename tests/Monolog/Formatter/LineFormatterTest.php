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

/**
 * @covers Monolog\Formatter\LineFormatter
 */
class LineFormatterTest extends \PHPUnit\Framework\TestCase
{
    public function testDefFormatWithString()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'WARNING',
            'channel' => 'log',
            'context' => [],
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
        ]);
        $this->assertEquals('['.date('Y-m-d').'] log.WARNING: foo [] []'."\n", $message);
    }

    public function testDefFormatWithArrayContext()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'context' => [
                'foo' => 'bar',
                'baz' => 'qux',
                'bool' => false,
                'null' => null,
            ],
        ]);
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: foo {"foo":"bar","baz":"qux","bool":false,"null":null} []'."\n", $message);
    }

    public function testDefFormatExtras()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['ip' => '127.0.0.1'],
            'message' => 'log',
        ]);
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log [] {"ip":"127.0.0.1"}'."\n", $message);
    }

    public function testFormatExtras()
    {
        $formatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra.file% %extra%\n", 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['ip' => '127.0.0.1', 'file' => 'test'],
            'message' => 'log',
        ]);
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log [] test {"ip":"127.0.0.1"}'."\n", $message);
    }

    public function testContextAndExtraOptionallyNotShownIfEmpty()
    {
        $formatter = new LineFormatter(null, 'Y-m-d', false, true);
        $message = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'log',
        ]);
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log  '."\n", $message);
    }

    public function testContextAndExtraReplacement()
    {
        $formatter = new LineFormatter('%context.foo% => %extra.foo%');
        $message = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['foo' => 'bar'],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['foo' => 'xbar'],
            'message' => 'log',
        ]);
        $this->assertEquals('bar => xbar', $message);
    }

    public function testDefFormatWithObject()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['foo' => new TestFoo, 'bar' => new TestBar, 'baz' => [], 'res' => fopen('php://memory', 'rb')],
            'message' => 'foobar',
        ]);

        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: foobar [] {"foo":{"Monolog\\\\Formatter\\\\TestFoo":{"foo":"fooValue"}},"bar":{"Monolog\\\\Formatter\\\\TestBar":"bar"},"baz":[],"res":"[resource(stream)]"}'."\n", $message);
    }

    public function testDefFormatWithException()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (RuntimeException(code: 0): Foo at '.substr($path, 1, -1).':'.(__LINE__ - 8).')"} []'."\n", $message);
    }

    public function testDefFormatWithExceptionAndStacktrace()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces();
        $message = $formatter->format([
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertRegexp('{^\['.date('Y-m-d').'] core\.CRITICAL: foobar \{"exception":"\[object] \(RuntimeException\(code: 0\): Foo at '.preg_quote(substr($path, 1, -1)).':'.(__LINE__ - 8).'\)\n\[stacktrace]\n#0}', $message);
    }

    public function testDefFormatWithPreviousException()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $previous = new \LogicException('Wut?');
        $message = $formatter->format([
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo', 0, $previous)],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (RuntimeException(code: 0): Foo at '.substr($path, 1, -1).':'.(__LINE__ - 8).')\n[previous exception] [object] (LogicException(code: 0): Wut? at '.substr($path, 1, -1).':'.(__LINE__ - 12).')"} []'."\n", $message);
    }

    public function testDefFormatWithSoapFaultException()
    {
        if (!class_exists('SoapFault')) {
            $this->markTestSkipped('Requires the soap extension');
        }

        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format([
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \SoapFault('foo', 'bar', 'hello', 'world')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (SoapFault(code: 0 faultcode: foo faultactor: hello detail: world): bar at '.substr($path, 1, -1).':'.(__LINE__ - 8).')"} []'."\n", $message);

        $message = $formatter->format([
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \SoapFault('foo', 'bar', 'hello', (object) ['bar' => (object) ['biz' => 'baz'], 'foo' => 'world'])],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (SoapFault(code: 0 faultcode: foo faultactor: hello detail: {\"bar\":{\"biz\":\"baz\"},\"foo\":\"world\"}): bar at '.substr($path, 1, -1).':'.(__LINE__ - 8).')"} []'."\n", $message);
    }

    public function testBatchFormat()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->formatBatch([
            [
                'level_name' => 'CRITICAL',
                'channel' => 'test',
                'message' => 'bar',
                'context' => [],
                'datetime' => new \DateTimeImmutable,
                'extra' => [],
            ],
            [
                'level_name' => 'WARNING',
                'channel' => 'log',
                'message' => 'foo',
                'context' => [],
                'datetime' => new \DateTimeImmutable,
                'extra' => [],
            ],
        ]);
        $this->assertEquals('['.date('Y-m-d').'] test.CRITICAL: bar [] []'."\n".'['.date('Y-m-d').'] log.WARNING: foo [] []'."\n", $message);
    }

    public function testFormatShouldStripInlineLineBreaks()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format(
            [
                'message' => "foo\nbar",
                'context' => [],
                'extra' => [],
            ]
        );

        $this->assertRegExp('/foo bar/', $message);
    }

    public function testFormatShouldNotStripInlineLineBreaksWhenFlagIsSet()
    {
        $formatter = new LineFormatter(null, 'Y-m-d', true);
        $message = $formatter->format(
            [
                'message' => "foo\nbar",
                'context' => [],
                'extra' => [],
            ]
        );

        $this->assertRegExp('/foo\nbar/', $message);
    }
}

class TestFoo
{
    public $foo = 'fooValue';
}

class TestBar
{
    public function __toString()
    {
        return 'bar';
    }
}
