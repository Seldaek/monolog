<?php

namespace Monolog\Formatter;

class JsonScalarFormatterTest extends \PHPUnit_Framework_TestCase
{
    private $formatter;

    public function setUp()
    {
        $this->formatter = new JsonScalarFormatter(new ScalarFormatter());
    }

    public function testFormat()
    {
        $input = [
            'key' => 'value',
            'array' => [
                'array_key' => 'array_value',
            ],
        ];

        $expectedOutput = '{"key":"value","array":"{\"array_key\":\"array_value\"}"}';

        $this->assertSame($expectedOutput, $this->formatter->format($input));
    }
}
