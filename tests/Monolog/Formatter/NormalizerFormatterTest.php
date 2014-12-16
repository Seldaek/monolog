<?php

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
 * @covers Monolog\Formatter\NormalizerFormatter
 */
class NormalizerFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormat()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->format(array(
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array('foo' => new TestFooNorm, 'bar' => new TestBarNorm, 'baz' => array(), 'res' => fopen('php://memory', 'rb')),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            ),
        ));

        $this->assertEquals(array(
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => date('Y-m-d'),
            'extra' => array(
                'foo' => '[object] (Monolog\\Formatter\\TestFooNorm: {"foo":"foo"})',
                'bar' => '[object] (Monolog\\Formatter\\TestBarNorm: {})',
                'baz' => array(),
                'res' => '[resource]',
            ),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ), $formatted);
    }

    public function testFormatExceptions()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $e = new \LogicException('bar');
        $e2 = new \RuntimeException('foo', 0, $e);
        $formatted = $formatter->format(array(
            'exception' => $e2,
        ));

        $this->assertGreaterThan(5, count($formatted['exception']['trace']));
        $this->assertTrue(isset($formatted['exception']['previous']));
        unset($formatted['exception']['trace'], $formatted['exception']['previous']);

        $this->assertEquals(array(
            'exception' => array(
                'class'   => get_class($e2),
                'message' => $e2->getMessage(),
                'file'   => $e2->getFile().':'.$e2->getLine(),
            )
        ), $formatted);
    }

    public function testBatchFormat()
    {
        $formatter = new NormalizerFormatter('Y-m-d');
        $formatted = $formatter->formatBatch(array(
            array(
                'level_name' => 'CRITICAL',
                'channel' => 'test',
                'message' => 'bar',
                'context' => array(),
                'datetime' => new \DateTime,
                'extra' => array(),
            ),
            array(
                'level_name' => 'WARNING',
                'channel' => 'log',
                'message' => 'foo',
                'context' => array(),
                'datetime' => new \DateTime,
                'extra' => array(),
            ),
        ));
        $this->assertEquals(array(
            array(
                'level_name' => 'CRITICAL',
                'channel' => 'test',
                'message' => 'bar',
                'context' => array(),
                'datetime' => date('Y-m-d'),
                'extra' => array(),
            ),
            array(
                'level_name' => 'WARNING',
                'channel' => 'log',
                'message' => 'foo',
                'context' => array(),
                'datetime' => date('Y-m-d'),
                'extra' => array(),
            ),
        ), $formatted);
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
        });

        $formatter = new NormalizerFormatter();
        $reflMethod = new \ReflectionMethod($formatter, 'toJson');
        $reflMethod->setAccessible(true);
        $res = $reflMethod->invoke($formatter, array($foo, $bar), true);

        restore_error_handler();

        $this->assertEquals(@json_encode(array($foo, $bar)), $res);
    }

    public function testIgnoresInvalidTypes()
    {
        // set up the recursion
        $resource = fopen(__FILE__, 'r');

        // set an error handler to assert that the error is not raised anymore
        $that = $this;
        set_error_handler(function ($level, $message, $file, $line, $context) use ($that) {
            if (error_reporting() & $level) {
                restore_error_handler();
                $that->fail("$message should not be raised");
            }
        });

        $formatter = new NormalizerFormatter();
        $reflMethod = new \ReflectionMethod($formatter, 'toJson');
        $reflMethod->setAccessible(true);
        $res = $reflMethod->invoke($formatter, array($resource), true);

        restore_error_handler();

        $this->assertEquals(@json_encode(array($resource)), $res);
    }

    public function testExceptionTraceWithArgs()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported in HHVM since it detects errors differently');
        }

        // This happens i.e. in React promises or Guzzle streams where stream wrappers are registered
        // and no file or line are included in the trace because it's treated as internal function
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            // This will contain $resource and $wrappedResource as arguments in the trace item
            $resource = fopen('php://memory', 'rw+');
            fwrite($resource, 'test_resource');
            $wrappedResource = new TestStreamFoo($resource);
            // Just do something stupid with a resource/wrapped resource as argument
            array_keys($wrappedResource);
        } catch (\Exception $e) {
            restore_error_handler();
        }

        $formatter = new NormalizerFormatter();
        $record = array('context' => array('exception' => $e));
        $result = $formatter->format($record);

        $this->assertRegExp(
            '%"resource":"\[resource\]"%',
            $result['context']['exception']['trace'][0]
        );

        if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
            $pattern = '%"wrappedResource":"\[object\] \(Monolog\\\\\\\\Formatter\\\\\\\\TestStreamFoo: \)"%';
        } else {
            $pattern = '%\\\\"resource\\\\":null%';
        }

        // Tests that the wrapped resource is ignored while encoding, only works for PHP <= 5.4
        $this->assertRegExp(
            $pattern,
            $result['context']['exception']['trace'][0]
        );
    }
}

class TestFooNorm
{
    public $foo = 'foo';
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
