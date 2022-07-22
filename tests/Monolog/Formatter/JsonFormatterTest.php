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

use JsonSerializable;
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
        $record['context'] = $record['extra'] = new \stdClass;
        $this->assertEquals(json_encode($record)."\n", $formatter->format($record));

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $record = $this->getRecord();
        $this->assertEquals('{"message":"test","context":{},"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record['datetime']->format('Y-m-d\TH:i:s.uP').'","extra":{}}', $formatter->format($record));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::format
     */
    public function testFormatWithPrettyPrint()
    {
        $formatter = new JsonFormatter();
        $formatter->setJsonPrettyPrint(true);
        $record = $this->getRecord();
        $record['context'] = $record['extra'] = new \stdClass;
        $this->assertEquals(json_encode($record, JSON_PRETTY_PRINT)."\n", $formatter->format($record));

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $formatter->setJsonPrettyPrint(true);
        $record = $this->getRecord();
        $this->assertEquals(
            '{
    "message": "test",
    "context": {},
    "level": 300,
    "level_name": "WARNING",
    "channel": "test",
    "datetime": "'.$record['datetime']->format('Y-m-d\TH:i:s.uP').'",
    "extra": {}
}',
            $formatter->format($record)
        );

        $formatter->setJsonPrettyPrint(false);
        $record = $this->getRecord();
        $this->assertEquals('{"message":"test","context":{},"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record['datetime']->format('Y-m-d\TH:i:s.uP').'","extra":{}}', $formatter->format($record));
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
            $value['context'] = $value['extra'] = new \stdClass;
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

    public function testMaxNormalizeDepth()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        $formatter->setMaxNormalizeDepth(1);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);

        $this->assertContextContainsFormattedException('"Over 1 levels deep, aborting normalization"', $message);
    }

    public function testMaxNormalizeItemCountWith0ItemsMax()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        $formatter->setMaxNormalizeDepth(9);
        $formatter->setMaxNormalizeItemCount(0);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);

        $this->assertEquals(
            '{"...":"Over 0 items (6 total), aborting normalization"}'."\n",
            $message
        );
    }

    public function testMaxNormalizeItemCountWith2ItemsMax()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        $formatter->setMaxNormalizeDepth(9);
        $formatter->setMaxNormalizeItemCount(2);
        $throwable = new \Error('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $throwable);

        $this->assertEquals(
            '{"level_name":"CRITICAL","channel":"core","...":"Over 2 items (6 total), aborting normalization"}'."\n",
            $message
        );
    }

    public function testDefFormatWithResource()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $record = $this->getRecord();
        $record['context'] = ['field_resource' => opendir(__DIR__)];
        $this->assertEquals('{"message":"test","context":{"field_resource":"[resource(stream)]"},"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record['datetime']->format('Y-m-d\TH:i:s.uP').'","extra":{}}', $formatter->format($record));
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
            '{"level_name":"CRITICAL","channel":"core","context":{"exception":'.$expected.'},"datetime":null,"extra":{},"message":"foobar"}'."\n",
            $actual
        );
    }

    /**
     * @param JsonFormatter $formatter
     * @param \Throwable    $exception
     *
     * @return string
     */
    private function formatRecordWithExceptionInContext(JsonFormatter $formatter, \Throwable $exception)
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

    public function testEmptyContextAndExtraFieldsCanBeIgnored()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true, true);

        $record = $formatter->format(array(
            'level' => 100,
            'level_name' => 'DEBUG',
            'channel' => 'test',
            'message' => 'Testing',
            'context' => array(),
            'extra' => array(),
        ));

        $this->assertSame(
            '{"level":100,"level_name":"DEBUG","channel":"test","message":"Testing"}'."\n",
            $record
        );
    }

    public function testFormatObjects()
    {
        $formatter = new JsonFormatter();

        $record = $formatter->format(array(
            'level' => 100,
            'level_name' => 'DEBUG',
            'channel' => 'test',
            'message' => 'Testing',
            'context' => array(
                'public' => new TestJsonNormPublic,
                'private' => new TestJsonNormPrivate,
                'withToStringAndJson' => new TestJsonNormWithToStringAndJson,
                'withToString' => new TestJsonNormWithToString,
            ),
            'extra' => array(),
        ));

        $this->assertSame(
            '{"level":100,"level_name":"DEBUG","channel":"test","message":"Testing","context":{"public":{"foo":"fooValue"},"private":{},"withToStringAndJson":["json serialized"],"withToString":"stringified"},"extra":{}}'."\n",
            $record
        );
    }
}

class TestJsonNormPublic
{
    public $foo = 'fooValue';
}

class TestJsonNormPrivate
{
    private $foo = 'fooValue';
}

class TestJsonNormWithToStringAndJson implements JsonSerializable
{
    public function jsonSerialize()
    {
        return ['json serialized'];
    }

    public function __toString()
    {
        return 'SHOULD NOT SHOW UP';
    }
}

class TestJsonNormWithToString
{
    public function __toString()
    {
        return 'stringified';
    }
}
