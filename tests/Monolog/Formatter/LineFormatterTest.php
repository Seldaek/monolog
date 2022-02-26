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

use Monolog\Test\TestCase;
use Monolog\Logger;

/**
 * @covers Monolog\Formatter\LineFormatter
 */
class LineFormatterTest extends TestCase
{
    public function testDefFormatWithString()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Logger::WARNING,
            'foo',
            channel: 'log',
        ));
        $this->assertEquals('['.date('Y-m-d').'] log.WARNING: foo [] []'."\n", $message);
    }

    public function testDefFormatWithArrayContext()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $message = $formatter->format($this->getRecord(
            Logger::ERROR,
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
            Logger::ERROR,
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
            Logger::ERROR,
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
            Logger::ERROR,
            'log',
            channel: 'meh',
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log  '."\n", $message);
    }

    public function testContextAndExtraReplacement()
    {
        $formatter = new LineFormatter('%context.foo% => %extra.foo%');
        $message = $formatter->format($this->getRecord(
            Logger::ERROR,
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
            Logger::ERROR,
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
            Logger::CRITICAL,
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
            Logger::CRITICAL,
            'foobar',
            channel: 'core',
            context: ['exception' => new \RuntimeException('Foo')],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertMatchesRegularExpression('{^\['.date('Y-m-d').'] core\.CRITICAL: foobar \{"exception":"\[object] \(RuntimeException\(code: 0\): Foo at '.preg_quote(substr($path, 1, -1)).':'.(__LINE__ - 5).'\)\n\[stacktrace]\n#0}', $message);
    }

    public function testDefFormatWithPreviousException()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $previous = new \LogicException('Wut?');
        $message = $formatter->format($this->getRecord(
            Logger::CRITICAL,
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
            Logger::CRITICAL,
            'foobar',
            channel: 'core',
            context: ['exception' => new \SoapFault('foo', 'bar', 'hello', 'world')],
        ));

        $path = str_replace('\\/', '/', json_encode(__FILE__));

        $this->assertEquals('['.date('Y-m-d').'] core.CRITICAL: foobar {"exception":"[object] (SoapFault(code: 0 faultcode: foo faultactor: hello detail: world): bar at '.substr($path, 1, -1).':'.(__LINE__ - 5).')"} []'."\n", $message);

        $message = $formatter->format($this->getRecord(
            Logger::CRITICAL,
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
                Logger::CRITICAL,
                'bar',
                channel: 'test',
            ),
            $this->getRecord(
                Logger::WARNING,
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
