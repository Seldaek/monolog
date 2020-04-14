<?php declare(strict_types=1);

namespace Monolog\Formatter;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monolog\Formatter\LokiFormatter
 */
class LokiFormatterTest extends TestCase
{
    private $logFormatter;

    public function setUp(): void
    {
        parent::setUp();
        $this->logFormatter = new LokiFormatter([], [], 'test');
    }

    public function testSimpleMessageWithEmptyExtraAndNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
    }

    public function testSimpleMessageWithNonEmptyExtraButNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['extra'] = ['ip' => '127.0.0.1'];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ip', $values);
        $this->assertEquals('127.0.0.1', $values['ip']);
    }

    public function testSimpleMessageWithEmptyExtraAndWithContext(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['ip' => '127.0.0.1'];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ctxt_ip', $values);
        $this->assertEquals('127.0.0.1', $values['ctxt_ip']);
    }

    public function testSimpleMessageWithEmptyExtraWithGlobalContextAndRecordContext(): void
    {
        $this->logFormatter = new LokiFormatter([], ['app' => 'myapp'], 'test');
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['ip' => '127.0.0.1'];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ctxt_app', $values);
        $this->assertArrayHasKey('ctxt_ip', $values);
        $this->assertEquals('myapp', $values['ctxt_app']);
        $this->assertEquals('127.0.0.1', $values['ctxt_ip']);
    }

    public function testSimpleMessageWithExtraObjectButNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['extra'] = [
            'foo' => new TestLokiFoo(),
            'bar' => new TestLokiBar(),
            'baz' => [],
            'res' => fopen('php://memory', 'rb'),
        ];

        $message = $this->logFormatter->format($logRecord);
        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('foo', $values);
        $this->assertArrayHasKey('bar', $values);
        $this->assertArrayHasKey('baz', $values);
        $this->assertArrayHasKey('res', $values);
        $this->assertEquals('{"Monolog\\\\Formatter\\\\TestLokiFoo":{"foo":"fooValue"}}', $values['foo']);
        $this->assertEquals('{"Monolog\\\\Formatter\\\\TestLokiBar":"bar"}', $values['bar']);
        $this->assertEquals('[]', $values['baz']);
        $this->assertEquals('[resource(stream)]', $values['res']);
    }

    public function testSimpleMessageWithEmptyExtraNoGlobalContextNorRecordContextWithGlobalLabels(): void
    {
        $this->logFormatter = new LokiFormatter(['app' => 'myapp'], [], 'test');
        $logRecord = $this->getLogRecord();
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $this->assertArrayHasKey('app', $message['stream']);
        $this->assertEquals('myapp', $message['stream']['app']);
    }

    public function testSimpleMessageWithEmptyExtraWithRecordContextLabels(): void
    {
        $this->logFormatter = new LokiFormatter([], [], 'test');
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['ip' => '127.0.0.1', 'labels' => ['app' => 'myapp']];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $this->assertArrayHasKey('app', $message['stream']);
        $this->assertEquals('myapp', $message['stream']['app']);

        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ctxt_ip', $values);
        $this->assertEquals('127.0.0.1', $values['ctxt_ip']);
    }

    public function testSimpleMessageWithContextException(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['exception' => new \RuntimeException('Foo')];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ctxt_exception', $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        $this->assertNotEmpty($arrayException['trace']);
        $this->assertSame('RuntimeException', $arrayException['class']);
        $this->assertSame('Foo', $arrayException['message']);
        $this->assertSame(__FILE__.':'.(__LINE__ - 13), $arrayException['file']);
        $this->assertArrayNotHasKey('previous', $arrayException);
    }

    public function testSimpleMessageWithContextExceptionWithPrevious(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['exception' => new \RuntimeException('Foo', 0, new \LogicException('Wut?'))];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ctxt_exception', $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        $this->assertNotEmpty($arrayException['trace']);
        $this->assertSame('RuntimeException', $arrayException['class']);
        $this->assertSame('Foo', $arrayException['message']);
        $this->assertSame(__FILE__.':'.(__LINE__ - 13), $arrayException['file']);
        $this->assertNotEmpty($arrayException['previous']);
        $this->assertSame('LogicException', $arrayException['previous']['class']);
        $this->assertSame('Wut?', $arrayException['previous']['message']);
    }

    public function testSimpleMessageWithSoapFault(): void
    {
        if (!class_exists('SoapFault')) {
            $this->markTestSkipped('Requires the soap extension');
        }

        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['exception' => new \SoapFault('foo', 'bar', 'hello', 'world')];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('ctxt_exception', $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        $this->assertNotEmpty($arrayException['trace']);
        $this->assertSame('SoapFault', $arrayException['class']);
        $this->assertSame('bar', $arrayException['message']);
        $this->assertSame(__FILE__.':'.(__LINE__ - 13), $arrayException['file']);
        $this->assertSame('foo', $arrayException['faultcode']);
        $this->assertSame('hello', $arrayException['faultactor']);
        $this->assertSame('world', $arrayException['detail']);
    }

    public function testBatchFormat(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord2 = $this->getLogRecord();
        $logRecord2['level_name'] = 'INFO';
        $logRecord2['message'] = 'bar';
        $messages = $this->logFormatter->formatBatch([$logRecord, $logRecord2]);

        $this->assertCount(2, $messages);
        $this->validateBaseStructure($messages[0], $logRecord);
        $this->validateBaseStructure($messages[1], $logRecord2);
    }

    public function testLineBreaksNotRemoved(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['message'] = "foo\nbar";
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
    }

    private function getLogRecord($level = Logger::WARNING): array
    {
        return [
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'log',
            'context' => [],
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];
    }

    private function validateBaseStructure($message, $logRecord): void
    {
        $this->assertArrayHasKey('stream', $message);
        $this->assertArrayHasKey('values', $message);
        $this->assertCount(1, $message['values']);
        $this->assertCount(2, $message['values'][0]);

        $labels = $message['stream'];
        $this->assertArrayHasKey('host', $labels);
        $this->assertArrayHasKey('level_name', $labels);
        $this->assertArrayHasKey('channel', $labels);

        $this->assertEquals($labels['host'], 'test');
        $this->assertEquals($labels['level_name'], $logRecord['level_name']);
        $this->assertEquals($labels['channel'], $logRecord['channel']);

        $values = json_decode($message['values'][0][1], true);
        $this->assertArrayHasKey('level_name', $values);
        $this->assertArrayHasKey('channel', $values);
        $this->assertArrayHasKey('message', $values);
        $this->assertArrayHasKey('datetime', $values);

        $this->assertEquals($values['level_name'], $logRecord['level_name']);
        $this->assertEquals($values['channel'], $logRecord['channel']);
        $this->assertEquals($values['message'], $logRecord['message']);
        $this->assertEquals($values['datetime'], $logRecord['datetime']->format(NormalizerFormatter::SIMPLE_DATE));
    }
}

class TestLokiFoo
{
    public $foo = 'fooValue';
}

class TestLokiBar
{
    public function __toString()
    {
        return 'bar';
    }
}
