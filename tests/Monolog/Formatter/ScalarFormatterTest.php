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

use Monolog\DateTimeImmutable;

class ScalarFormatterTest extends \PHPUnit\Framework\TestCase
{
    private $formatter;

    public function setUp(): void
    {
        $this->formatter = new ScalarFormatter();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->formatter);
    }

    public function buildTrace(\Exception $e)
    {
        $data = [];
        $trace = $e->getTrace();
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                $data[] = $frame['file'].':'.$frame['line'];
            }
        }

        return $data;
    }

    public function encodeJson($data)
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function testFormat()
    {
        $exception = new \Exception('foo');
        $formatted = $this->formatter->format([
            'foo' => 'string',
            'bar' => 1,
            'baz' => false,
            'bam' => [1, 2, 3],
            'bat' => ['foo' => 'bar'],
            'bap' => $dt = new DateTimeImmutable(true),
            'ban' => $exception,
        ]);

        $this->assertSame([
            'foo' => 'string',
            'bar' => 1,
            'baz' => false,
            'bam' => $this->encodeJson([1, 2, 3]),
            'bat' => $this->encodeJson(['foo' => 'bar']),
            'bap' => (string) $dt,
            'ban' => $this->encodeJson([
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile() . ':' . $exception->getLine(),
                'trace'   => $this->buildTrace($exception),
            ]),
        ], $formatted);
    }

    public function testFormatWithErrorContext()
    {
        $context = ['file' => 'foo', 'line' => 1];
        $formatted = $this->formatter->format([
            'context' => $context,
        ]);

        $this->assertSame([
            'context' => $this->encodeJson($context),
        ], $formatted);
    }

    public function testFormatWithExceptionContext()
    {
        $exception = new \Exception('foo');
        $formatted = $this->formatter->format([
            'context' => [
                'exception' => $exception,
            ],
        ]);

        $this->assertSame([
            'context' => $this->encodeJson([
                'exception' => [
                    'class'   => get_class($exception),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                    'file'    => $exception->getFile() . ':' . $exception->getLine(),
                    'trace'   => $this->buildTrace($exception),
                ],
            ]),
        ], $formatted);
    }
}
