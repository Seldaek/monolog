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

use Monolog\Logger;
use Monolog\Test\TestCase;

class JsonFormatterTest extends TestCase
{
    /**
     * @covers Monolog\Formatter\JsonFormatter::__construct
     * @covers Monolog\Formatter\JsonFormatter::getBatchMode
     * @covers Monolog\Formatter\JsonFormatter::isAppendingNewlines
     */
    public function testConstruct()
    {
        $formatter = new JsonFormatter();
        $this->assertEquals(JsonFormatter::BATCH_MODE_JSON, $formatter->getBatchMode());
        $this->assertEquals(true, $formatter->isAppendingNewlines());
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, false);
        $this->assertEquals(JsonFormatter::BATCH_MODE_NEWLINES, $formatter->getBatchMode());
        $this->assertEquals(false, $formatter->isAppendingNewlines());
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::format
     */
    public function testFormat()
    {
        $formatter = new JsonFormatter();
        $record = $this->getRecord();
        $this->assertEquals(json_encode($record)."\n", $formatter->format($record));

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $record = $this->getRecord();
        $this->assertEquals('{"message":"test","context":[],"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record['datetime']->format('Y-m-d\TH:i:s.uP').'","extra":[]}', $formatter->format($record));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::formatBatch
     * @covers Monolog\Formatter\JsonFormatter::formatBatchJson
     */
    public function testFormatBatch()
    {
        $formatter = new JsonFormatter();
        $records = [
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        ];
        $this->assertEquals(json_encode($records), $formatter->formatBatch($records));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::formatBatch
     * @covers Monolog\Formatter\JsonFormatter::formatBatchNewlines
     */
    public function testFormatBatchNewlines()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES);
        $records = $expected = [
            $this->getRecord(Logger::WARNING),
            $this->getRecord(Logger::DEBUG),
        ];
        array_walk($expected, function (&$value, $key) {
            $value = json_encode($value);
        });
        $this->assertEquals(implode("\n", $expected), $formatter->formatBatch($records));
    }

    public function testDefFormatWithException()
    {
        $formatter = new JsonFormatter();
        $exception = new \RuntimeException('Foo');
        $formattedException = $this->formatException($exception);

        $message = $this->formatRecordWithExceptionInContext($formatter, $exception);

        $this->assertContextContainsFormattedException($formattedException, $message);
    }

    public function testDefFormatWithPreviousException()
    {
        $formatter = new JsonFormatter();
        $exception = new \RuntimeException('Foo', 0, new \LogicException('Wut?'));
        $formattedPrevException = $this->formatException($exception->getPrevious());
        $formattedException = $this->formatException($exception, $formattedPrevException);

        $message = $this->formatRecordWithExceptionInContext($formatter, $exception);

        $this->assertContextContainsFormattedException($formattedException, $message);
    }

    public function testDefFormatWithThrowable()
    {
        $formatter = new JsonFormatter();
        $throwable = new \Error('Foo');
        $formattedThrowable = $this->formatException($throwable);

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);

        $this->assertContextContainsFormattedException($formattedThrowable, $message);
    }

    /**
     * @param string $expected
     * @param string $actual
     *
     * @internal param string $exception
     */
    private function assertContextContainsFormattedException($expected, $actual)
    {
        $this->assertEquals(
            '{"level_name":"CRITICAL","channel":"core","context":{"exception":'.$expected.'},"datetime":null,"extra":[],"message":"foobar"}'."\n",
            $actual
        );
    }

    /**
     * @param JsonFormatter         $formatter
     * @param \Exception|\Throwable $exception
     *
     * @return string
     */
    private function formatRecordWithExceptionInContext(JsonFormatter $formatter, $exception)
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

    /**
     * @param \Exception|\Throwable $exception
     *
     * @return string
     */
    private function formatExceptionFilePathWithLine($exception)
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $path = substr(json_encode($exception->getFile(), $options), 1, -1);

        return $path . ':' . $exception->getLine();
    }

    /**
     * @param \Exception|\Throwable $exception
     *
     * @param null|string $previous
     *
     * @return string
     */
    private function formatException($exception, $previous = null)
    {
        $formattedException =
            '{"class":"' . get_class($exception) .
            '","message":"' . $exception->getMessage() .
            '","code":' . $exception->getCode() .
            ',"file":"' . $this->formatExceptionFilePathWithLine($exception) .
            ($previous ? '","previous":' . $previous : '"') .
            '}';

        return $formattedException;
    }
}
