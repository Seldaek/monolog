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

use Monolog\JsonSerializableDateTimeImmutable;

class ScalarFormatterTest extends \Monolog\Test\MonologTestCase
{
    private ScalarFormatter $formatter;

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
        $formatted = $this->formatter->format($this->getRecord(context: [
            'foo' => 'string',
            'bar' => 1,
            'baz' => false,
            'bam' => [1, 2, 3],
            'bat' => ['foo' => 'bar'],
            'bap' => $dt = new JsonSerializableDateTimeImmutable(true),
            'ban' => $exception,
        ]));

        $this->assertSame($this->encodeJson([
            'foo' => 'string',
            'bar' => 1,
            'baz' => false,
            'bam' => [1, 2, 3],
            'bat' => ['foo' => 'bar'],
            'bap' => (string) $dt,
            'ban' => [
                'class'   => \get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile() . ':' . $exception->getLine(),
                'trace'   => $this->buildTrace($exception),
            ],
        ]), $formatted['context']);
    }

    public function testFormatWithErrorContext()
    {
        $context = ['file' => 'foo', 'line' => 1];
        $formatted = $this->formatter->format($this->getRecord(
            context: $context,
        ));

        $this->assertSame($this->encodeJson($context), $formatted['context']);
    }

    public function testFormatWithExceptionContext()
    {
        $exception = new \Exception('foo');
        $formatted = $this->formatter->format($this->getRecord(context: [
            'exception' => $exception,
        ]));

        $this->assertSame($this->encodeJson([
            'exception' => [
                'class'   => \get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile() . ':' . $exception->getLine(),
                'trace'   => $this->buildTrace($exception),
            ],
        ]), $formatted['context']);
    }
}
