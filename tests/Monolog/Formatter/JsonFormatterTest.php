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

use Monolog\Level;
use Monolog\LogRecord;
use JsonSerializable;
use Monolog\Test\MonologTestCase;

class JsonFormatterTest extends MonologTestCase
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
        $this->assertEquals(json_encode($record->toArray(), JSON_FORCE_OBJECT)."\n", $formatter->format($record));

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $record = $this->getRecord();
        $this->assertEquals('{"message":"test","context":{},"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record->datetime->format('Y-m-d\TH:i:s.uP').'","extra":{}}', $formatter->format($record));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::format
     */
    public function testFormatWithPrettyPrint()
    {
        $formatter = new JsonFormatter();
        $formatter->setJsonPrettyPrint(true);
        $record = $this->getRecord();
        $this->assertEquals(json_encode($record->toArray(), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT)."\n", $formatter->format($record));

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
    "datetime": "'.$record->datetime->format('Y-m-d\TH:i:s.uP').'",
    "extra": {}
}',
            $formatter->format($record)
        );

        $formatter->setJsonPrettyPrint(false);
        $record = $this->getRecord();
        $this->assertEquals('{"message":"test","context":{},"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record->datetime->format('Y-m-d\TH:i:s.uP').'","extra":{}}', $formatter->format($record));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::formatBatch
     * @covers Monolog\Formatter\JsonFormatter::formatBatchJson
     */
    public function testFormatBatch()
    {
        $formatter = new JsonFormatter();
        $records = [
            $this->getRecord(Level::Warning),
            $this->getRecord(Level::Debug),
        ];
        $expected = array_map(fn (LogRecord $record) => json_encode($record->toArray(), JSON_FORCE_OBJECT), $records);
        $this->assertEquals('['.implode(',', $expected).']', $formatter->formatBatch($records));
    }

    /**
     * @covers Monolog\Formatter\JsonFormatter::formatBatch
     * @covers Monolog\Formatter\JsonFormatter::formatBatchNewlines
     */
    public function testFormatBatchNewlines()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES);
        $records = [
            $this->getRecord(Level::Warning),
            $this->getRecord(Level::Debug),
        ];
        $expected = array_map(fn (LogRecord $record) => json_encode($record->toArray(), JSON_FORCE_OBJECT), $records);
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

    public function testBasePathWithException(): void
    {
        $formatter = new JsonFormatter();
        $formatter->setBasePath(\dirname(\dirname(\dirname(__DIR__))));
        $exception = new \RuntimeException('Foo');

        $message = $this->formatRecordWithExceptionInContext($formatter, $exception);

        $parsed = json_decode($message, true);
        self::assertSame('tests/Monolog/Formatter/JsonFormatterTest.php:' . (__LINE__ - 5), $parsed['context']['exception']['file']);
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
            '{"...":"Over 0 items (7 total), aborting normalization"}'."\n",
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
            '{"message":"foobar","context":{"exception":{"class":"Error","message":"Foo","code":0,"file":"'.__FILE__.':'.(__LINE__ - 5).'"}},"...":"Over 2 items (7 total), aborting normalization"}'."\n",
            $message
        );
    }

    public function testDefFormatWithResource()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
        $record = $this->getRecord(
            context: ['field_resource' => opendir(__DIR__)],
        );
        $this->assertEquals('{"message":"test","context":{"field_resource":"[resource(stream)]"},"level":300,"level_name":"WARNING","channel":"test","datetime":"'.$record->datetime->format('Y-m-d\TH:i:s.uP').'","extra":{}}', $formatter->format($record));
    }

    /**
     * @internal param string $exception
     */
    private function assertContextContainsFormattedException(string $expected, string $actual)
    {
        $this->assertEquals(
            '{"message":"foobar","context":{"exception":'.$expected.'},"level":500,"level_name":"CRITICAL","channel":"core","datetime":"2022-02-22T00:00:00+00:00","extra":{}}'."\n",
            $actual
        );
    }

    private function formatRecordWithExceptionInContext(JsonFormatter $formatter, \Throwable $exception): string
    {
        $message = $formatter->format($this->getRecord(
            Level::Critical,
            'foobar',
            channel: 'core',
            context: ['exception' => $exception],
            datetime: new \DateTimeImmutable('2022-02-22 00:00:00'),
        ));

        return $message;
    }

    /**
     * @param \Exception|\Throwable $exception
     */
    private function formatExceptionFilePathWithLine($exception): string
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $path = substr(json_encode($exception->getFile(), $options), 1, -1);

        return $path . ':' . $exception->getLine();
    }

    /**
     * @param \Exception|\Throwable $exception
     */
    private function formatException($exception, ?string $previous = null): string
    {
        $formattedException =
            '{"class":"' . \get_class($exception) .
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

    public function testCanNormalizeIncompleteObject(): void
    {
        $serialized = "O:17:\"Monolog\TestClass\":1:{s:23:\"\x00Monolog\TestClass\x00name\";s:4:\"test\";}";
        $object = unserialize($serialized);

        $formatter = new JsonFormatter();
        $record = $this->getRecord(context: ['object' => $object], datetime: new \DateTimeImmutable('2022-02-22 00:00:00'));
        $result = $formatter->format($record);

        self::assertSame('{"message":"test","context":{"object":{"__PHP_Incomplete_Class_Name":"Monolog\\\\TestClass"}},"level":300,"level_name":"WARNING","channel":"test","datetime":"2022-02-22T00:00:00+00:00","extra":{}}'."\n", $result);
    }

    public function testEmptyContextAndExtraFieldsCanBeIgnored()
    {
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true, true);

        $record = $formatter->format($this->getRecord(
            Level::Debug,
            'Testing',
            channel: 'test',
            datetime: new \DateTimeImmutable('2022-02-22 00:00:00'),
        ));

        $this->assertSame(
            '{"message":"Testing","level":100,"level_name":"DEBUG","channel":"test","datetime":"2022-02-22T00:00:00+00:00"}'."\n",
            $record
        );
    }

    public function testFormatObjects()
    {
        $formatter = new JsonFormatter();

        $record = $formatter->format($this->getRecord(
            Level::Debug,
            'Testing',
            channel: 'test',
            datetime: new \DateTimeImmutable('2022-02-22 00:00:00'),
            context: [
                'public' => new TestJsonNormPublic,
                'private' => new TestJsonNormPrivate,
                'withToStringAndJson' => new TestJsonNormWithToStringAndJson,
                'withToString' => new TestJsonNormWithToString,
            ],
        ));

        $this->assertSame(
            '{"message":"Testing","context":{"public":{"foo":"fooValue"},"private":{},"withToStringAndJson":["json serialized"],"withToString":"stringified"},"level":100,"level_name":"DEBUG","channel":"test","datetime":"2022-02-22T00:00:00+00:00","extra":{}}'."\n",
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
    public function jsonSerialize(): mixed
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
