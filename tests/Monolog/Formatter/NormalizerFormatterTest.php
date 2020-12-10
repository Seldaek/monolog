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

use PHPUnit\Framework\TestCase;

/**
 * @covers Monolog\Formatter\NormalizerFormatter
 */
class NormalizerFormatterTest extends TestCase
{
    public function testFormat()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->format([
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable,
            'extra' => ['foo' => new TestFooNorm, 'bar' => new TestBarNorm, 'baz' => [], 'res' => fopen('php://memory', 'rb')],
            'context' => [
                'foo' => 'bar',
                'baz' => 'qux',
                'inf' => INF,
                '-inf' => -INF,
                'nan' => acos(4),
            ],
        ]);

        $this->assertEquals([
            'level_name' => 'ERROR',
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
        $formatted = $formatter->format([
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
        $formatted = $formatter->format([
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
        $formatted = $formatter->format([
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
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Could not convert to string');
        $formatter->format([
            'myObject' => new TestToStringError(),
        ]);
    }

    public function testBatchFormat()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->formatBatch([
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
        $this->assertEquals([
            [
                'level_name' => 'CRITICAL',
                'channel' => 'test',
                'message' => 'bar',
                'context' => [],
                'datetime' => date('Y-m-d'),
                'extra' => [],
            ],
            [
                'level_name' => 'WARNING',
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
        $formatter->format($y);
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

        $res = $formatter->format(array(
            'level_name' => 'CRITICAL',
            'channel' => 'test',
            'message' => 'bar',
            'context' => array($largeArray),
            'datetime' => new \DateTime,
            'extra' => array(),
        ));

        $this->assertCount(1000, $res['context'][0]);
        $this->assertArrayNotHasKey('...', $res['context'][0]);
    }

    public function testNormalizeHandleLargeArrays()
    {
        $formatter = new NormalizerFormatter();
        $largeArray = range(1, 2000);

        $res = $formatter->format(array(
            'level_name' => 'CRITICAL',
            'channel' => 'test',
            'message' => 'bar',
            'context' => array($largeArray),
            'datetime' => new \DateTime,
            'extra' => array(),
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
            ["..." => "Over 0 items (6 total), aborting normalization"],
            $message
        );
    }

    public function testMaxNormalizeItemCountWith3ItemsMax()
    {
        $formatter = new NormalizerFormatter();
        $formatter->setMaxNormalizeDepth(9);
        $formatter->setMaxNormalizeItemCount(2);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);

        $this->assertEquals(
            ["level_name" => "CRITICAL", "channel" => "core", "..." => "Over 2 items (6 total), aborting normalization"],
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
        $record = ['context' => ['exception' => $e]];
        $result = $formatter->format($record);

        $this->assertSame(
            __FILE__.':'.(__LINE__-9),
            $result['context']['exception']['trace'][0]
        );
    }

    /**
     * @param NormalizerFormatter $formatter
     * @param \Throwable          $exception
     *
     * @return string
     */
    private function formatRecordWithExceptionInContext(NormalizerFormatter $formatter, \Throwable $exception)
    {
        $message = $formatter->format([
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => $exception],
            'datetime' => null,
            'extra' => [],
            'message' => 'foobar',
        ]);

        return $message;
    }

    public function testExceptionTraceDoesNotLeakCallUserFuncArgs()
    {
        try {
            $arg = new TestInfoLeak;
            call_user_func(array($this, 'throwHelper'), $arg, $dt = new \DateTime());
        } catch (\Exception $e) {
        }

        $formatter = new NormalizerFormatter();
        $record = array('context' => array('exception' => $e));
        $result = $formatter->format($record);

        $this->assertSame(
            __FILE__ .':'.(__LINE__-9),
            $result['context']['exception']['trace'][0]
        );
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
