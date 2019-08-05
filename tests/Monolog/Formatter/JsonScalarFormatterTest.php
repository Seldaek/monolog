<?php declare(strict_types=1);

namespace Monolog\Formatter;

class JsonScalarFormatterTest extends \PHPUnit\Framework\TestCase
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
