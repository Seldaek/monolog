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
use Monolog\Level;

/**
 * @covers Monolog\Formatter\NormalizerFormatter
 */
class NormalizerFormatterTest extends TestCase
{
    public function testFormat()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->format($this->getRecord(
            Level::Error,
            'foo',
            channel: 'meh',
            extra: ['foo' => new TestFooNorm, 'bar' => new TestBarNorm, 'baz' => [], 'res' => fopen('php://memory', 'rb')],
            context: [
                'foo' => 'bar',
                'baz' => 'qux',
                'inf' => INF,
                '-inf' => -INF,
                'nan' => acos(4),
            ],
        ));

        $this->assertEquals([
            'level_name' => Level::Error->getName(),
            'level' => Level::Error->value,
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => date('Y-m-d'),
            'extra' => [
                'foo' => ['Monolog\\Formatter\\TestFooNorm' => ["foo" => "fooValue"]],
                'bar' => ['Monolog\\Formatter\\TestBarNorm' => 'bar'],
                'baz' => [],
                'res' => '[resource(stream)]',
            ],
            'context' => [
                'foo' => 'bar',
                'baz' => 'qux',
                'inf' => 'INF',
                '-inf' => '-INF',
                'nan' => 'NaN',
            ],
        ], $formatted);
    }

    public function testFormatExceptions()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $e = new \LogicException('bar');
        $e2 = new \RuntimeException('foo', 0, $e);
        $formatted = $formatter->normalizeValue([
            'exception' => $e2,
        ]);

        $this->assertGreaterThan(5, count($formatted['exception']['trace']));
        $this->assertTrue(isset($formatted['exception']['previous']));
        unset($formatted['exception']['trace'], $formatted['exception']['previous']);

        $this->assertEquals([
            'exception' => [
                'class'   => get_class($e2),
                'message' => $e2->getMessage(),
                'code'    => $e2->getCode(),
                'file'    => $e2->getFile().':'.$e2->getLine(),
            ],
        ], $formatted);
    }

    public function testFormatSoapFaultException()
    {
        if (!class_exists('SoapFault')) {
            $this->markTestSkipped('Requires the soap extension');
        }

        $formatter = new NormalizerFormatter('Y-m-d');
        $e = new \SoapFault('foo', 'bar', 'hello', 'world');
        $formatted = $formatter->normalizeValue([
            'exception' => $e,
        ]);

        unset($formatted['exception']['trace']);

        $this->assertEquals([
            'exception' => [
                'class' => 'SoapFault',
                'message' => 'bar',
                'code' => 0,
                'file' => $e->getFile().':'.$e->getLine(),
                'faultcode' => 'foo',
                'faultactor' => 'hello',
                'detail' => 'world',
            ],
        ], $formatted);

        $formatter = new NormalizerFormatter('Y-m-d');
        $e = new \SoapFault('foo', 'bar', 'hello', (object) ['bar' => (object) ['biz' => 'baz'], 'foo' => 'world']);
        $formatted = $formatter->normalizeValue([
            'exception' => $e,
        ]);

        unset($formatted['exception']['trace']);

        $this->assertEquals([
            'exception' => [
                'class' => 'SoapFault',
                'message' => 'bar',
                'code' => 0,
                'file' => $e->getFile().':'.$e->getLine(),
                'faultcode' => 'foo',
                'faultactor' => 'hello',
                'detail' => '{"bar":{"biz":"baz"},"foo":"world"}',
            ],
        ], $formatted);
    }

    public function testFormatToStringExceptionHandle()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->format($this->getRecord(context: [
            'myObject' => new TestToStringError(),
        ]));
        $this->assertEquals(
            [
                'level_name' => Level::Warning->getName(),
                'level' => Level::Warning->value,
                'channel' => 'test',
                'message' => 'test',
                'context' => [
                    'myObject' => [
                        TestToStringError::class => [],
                    ],
                ],
                'datetime' => date('Y-m-d'),
                'extra' => [],
            ],
            $formatted
        );
    }

    public function testBatchFormat()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->formatBatch([
            $this->getRecord(Level::Critical, 'bar', channel: 'test'),
            $this->getRecord(Level::Warning, 'foo', channel: 'log'),
        ]);
        $this->assertEquals([
            [
                'level_name' => Level::Critical->getName(),
                'level' => Level::Critical->value,
                'channel' => 'test',
                'message' => 'bar',
                'context' => [],
                'datetime' => date('Y-m-d'),
                'extra' => [],
            ],
            [
                'level_name' => Level::Warning->getName(),
                'level' => Level::Warning->value,
                'channel' => 'log',
                'message' => 'foo',
                'context' => [],
                'datetime' => date('Y-m-d'),
                'extra' => [],
            ],
        ], $formatted);
    }

    /**
     * Test issue #137
     */
    public function testIgnoresRecursiveObjectReferences()
    {
        // set up the recursion
        $foo = new \stdClass();
        $bar = new \stdClass();

        $foo->bar = $bar;
        $bar->foo = $foo;

        // set an error handler to assert that the error is not raised anymore
        $that = $this;
        set_error_handler(function ($level, $message, $file, $line, $context) use ($that) {
            if (error_reporting() & $level) {
                restore_error_handler();
                $that->fail("$message should not be raised");
            }

            return true;
        });

        $formatter = new NormalizerFormatter();
        $reflMethod = new \ReflectionMethod($formatter, 'toJson');
        $reflMethod->setAccessible(true);
        $res = $reflMethod->invoke($formatter, [$foo, $bar], true);

        restore_error_handler();

        $this->assertEquals('[{"bar":{"foo":null}},{"foo":{"bar":null}}]', $res);
    }

    public function testCanNormalizeReferences()
    {
        $formatter = new NormalizerFormatter();
        $x = ['foo' => 'bar'];
        $y = ['x' => &$x];
        $x['y'] = &$y;
        $formatter->normalizeValue($y);
    }

    public function testToJsonIgnoresInvalidTypes()
    {
        // set up the invalid data
        $resource = fopen(__FILE__, 'r');

        // set an error handler to assert that the error is not raised anymore
        $that = $this;
        set_error_handler(function ($level, $message, $file, $line, $context) use ($that) {
            if (error_reporting() & $level) {
                restore_error_handler();
                $that->fail("$message should not be raised");
            }

            return true;
        });

        $formatter = new NormalizerFormatter();
        $reflMethod = new \ReflectionMethod($formatter, 'toJson');
        $reflMethod->setAccessible(true);
        $res = $reflMethod->invoke($formatter, [$resource], true);

        restore_error_handler();

        $this->assertEquals('[null]', $res);
    }

    public function testNormalizeHandleLargeArraysWithExactly1000Items()
    {
        $formatter = new NormalizerFormatter();
        $largeArray = range(1, 1000);

        $res = $formatter->format($this->getRecord(
            Level::Critical,
            'bar',
            channel: 'test',
            context: [$largeArray],
        ));

        $this->assertCount(1000, $res['context'][0]);
        $this->assertArrayNotHasKey('...', $res['context'][0]);
    }

    public function testNormalizeHandleLargeArrays()
    {
        $formatter = new NormalizerFormatter();
        $largeArray = range(1, 2000);

        $res = $formatter->format($this->getRecord(
            Level::Critical,
            'bar',
            channel: 'test',
            context: [$largeArray],
        ));

        $this->assertCount(1001, $res['context'][0]);
        $this->assertEquals('Over 1000 items (2000 total), aborting normalization', $res['context'][0]['...']);
    }

    public function testIgnoresInvalidEncoding()
    {
        $formatter = new NormalizerFormatter();
        $reflMethod = new \ReflectionMethod($formatter, 'toJson');
        $reflMethod->setAccessible(true);

        // send an invalid unicode sequence as a object that can't be cleaned
        $record = new \stdClass;
        $record->message = "\xB1\x31";

        $this->assertsame('{"message":"�1"}', $reflMethod->invoke($formatter, $record));
    }

    public function testConvertsInvalidEncodingAsLatin9()
    {
        $formatter = new NormalizerFormatter();
        $reflMethod = new \ReflectionMethod($formatter, 'toJson');
        $reflMethod->setAccessible(true);

        $res = $reflMethod->invoke($formatter, ['message' => "\xA4\xA6\xA8\xB4\xB8\xBC\xBD\xBE"]);

        $this->assertSame('{"message":"��������"}', $res);
    }

    public function testMaxNormalizeDepth()
    {
        $formatter = new NormalizerFormatter();
        $formatter->setMaxNormalizeDepth(1);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);
        $this->assertEquals(
            'Over 1 levels deep, aborting normalization',
            $message['context']['exception']
        );
    }

    public function testMaxNormalizeItemCountWith0ItemsMax()
    {
        $formatter = new NormalizerFormatter();
        $formatter->setMaxNormalizeDepth(9);
        $formatter->setMaxNormalizeItemCount(0);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);
        $this->assertEquals(
            ["..." => "Over 0 items (7 total), aborting normalization"],
            $message
        );
    }

    public function testMaxNormalizeItemCountWith2ItemsMax()
    {
        $formatter = new NormalizerFormatter();
        $formatter->setMaxNormalizeDepth(9);
        $formatter->setMaxNormalizeItemCount(2);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);

        unset($message['context']['exception']['trace']);
        unset($message['context']['exception']['file']);
        $this->assertEquals(
            [
                "message" => "foobar",
                "context" => ['exception' => [
                    'class' => 'Error',
                    'message' => 'Foo',
                    'code' => 0,
               ]],
                "..." => "Over 2 items (7 total), aborting normalization",
            ],
            $message
        );
    }

    public function testExceptionTraceWithArgs()
    {
        try {
            // This will contain $resource and $wrappedResource as arguments in the trace item
            $resource = fopen('php://memory', 'rw+');
            fwrite($resource, 'test_resource');
            $wrappedResource = new TestFooNorm;
            $wrappedResource->foo = $resource;
            // Just do something stupid with a resource/wrapped resource as argument
            $arr = [$wrappedResource, $resource];
            // modifying the array inside throws a "usort(): Array was modified by the user comparison function"
            usort($arr, function ($a, $b) {
                throw new \ErrorException('Foo');
            });
        } catch (\Throwable $e) {
        }

        $formatter = new NormalizerFormatter();
        $record = $this->getRecord(context: ['exception' => $e]);
        $result = $formatter->format($record);

        // See https://github.com/php/php-src/issues/8810 fixed in PHP 8.2
        $offset = PHP_VERSION_ID >= 80200 ? 13 : 11;
        $this->assertSame(
            __FILE__.':'.(__LINE__ - $offset),
            $result['context']['exception']['trace'][0]
        );
    }

    private function formatRecordWithExceptionInContext(NormalizerFormatter $formatter, \Throwable $exception): array
    {
        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => $exception],
        ));

        return $message;
    }

    public function testExceptionTraceDoesNotLeakCallUserFuncArgs()
    {
        try {
            $arg = new TestInfoLeak;
            call_user_func([$this, 'throwHelper'], $arg, $dt = new \DateTime());
        } catch (\Exception $e) {
        }

        $formatter = new NormalizerFormatter();
        $record = $this->getRecord(context: ['exception' => $e]);
        $result = $formatter->format($record);

        $this->assertSame(
            __FILE__ .':'.(__LINE__-9),
            $result['context']['exception']['trace'][0]
        );
    }

    public function testCanNormalizeIncompleteObject(): void
    {
        $serialized = "O:17:\"Monolog\TestClass\":1:{s:23:\"\x00Monolog\TestClass\x00name\";s:4:\"test\";}";
        $object = unserialize($serialized);

        $formatter = new NormalizerFormatter();
        $record = $this->getRecord(context: ['object' => $object]);
        $result = $formatter->format($record);

        $this->assertEquals([
            '__PHP_Incomplete_Class' => 'Monolog\\TestClass',
        ], $result['context']['object']);
    }

    private function throwHelper($arg)
    {
        throw new \RuntimeException('Thrown');
    }
}

class TestFooNorm
{
    public $foo = 'fooValue';
}

class TestBarNorm
{
    public function __toString()
    {
        return 'bar';
    }
}

class TestStreamFoo
{
    public $foo;
    public $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
        $this->foo = 'BAR';
    }

    public function __toString()
    {
        fseek($this->resource, 0);

        return $this->foo . ' - ' . (string) stream_get_contents($this->resource);
    }
}

class TestToStringError
{
    public function __toString()
    {
        throw new \RuntimeException('Could not convert to string');
    }
}

class TestInfoLeak
{
    public function __toString()
    {
        return 'Sensitive information';
    }
}
