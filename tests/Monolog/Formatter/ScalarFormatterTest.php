<?php
namespace Monolog\Formatter;

class ScalarFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->formatter = new ScalarFormatter();
    }

    public function encodeJson($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    public function testFormat()
    {
        $exception = new \Exception('foo');
        $formatted = $this->formatter->format(array(
            'foo' => 'string',
            'bar' => 1,
            'baz' => false,
            'bam' => array(1,2,3),
            'bat' => array('foo' => 'bar'),
            'bap' => \DateTime::createFromFormat(\DateTime::ISO8601, '1970-01-01T00:00:00+0000'),
            'ban' => $exception
        ));

        $this->assertSame(array(
            'foo' => 'string',
            'bar' => 1,
            'baz' => false,
            'bam' => $this->encodeJson(array(1,2,3)),
            'bat' => $this->encodeJson(array('foo' => 'bar')),
            'bap' => '1970-01-01T00:00:00+0000',
            'ban' => $this->encodeJson(array(
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'class'   => get_class($exception),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $exception->getTraceAsString(),
                'debug'   => $exception->getTrace()
            ))
        ), $formatted);
    }

    public function testFormatWithErrorContext()
    {
        $context = array('file' => 'foo', 'line' => 1);
        $formatted = $this->formatter->format(array(
            'context' => $context
        ));

        $this->assertSame(array(
            'context' => $this->encodeJson($context)
        ), $formatted);
    }

    public function testFormatWithExceptionContext()
    {
        $exception = new \Exception('foo');
        $formatted = $this->formatter->format(array(
            'context' => array(
                'exception' => $exception
            )
        ));

        $this->assertSame(array(
            'context' => $this->encodeJson(array(
                'exception' => array(
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                    'class'   => get_class($exception),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'trace'   => $exception->getTraceAsString(),
                    'debug'   => $exception->getTrace()
                )
            ))
        ), $formatted);
    }
}
