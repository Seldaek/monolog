<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use Monolog\Test\TestCase;

/**
 * @covers \Monolog\TestLogger
 */
class TestLoggerTest extends TestCase
{
    /**
     * @dataProvider methodProvider
     */
    public function testHandler($method)
    {
        $handler = new TestLogger();
        $level = strtolower($method);
        $record = ['level' => $level, 'message' => 'test' . $method, 'context' => []];
        $this->assertFalse($handler->hasRecords($level));
        $this->assertFalse($handler->hasRecord($record, $level));
        $this->assertFalse($handler->{'has'.$method}($record), 'has'.$method);
        $this->assertFalse($handler->{'has'.$method.'ThatContains'}('test'), 'has'.$method.'ThatContains');
        $this->assertFalse($handler->{'has'.$method.'ThatPasses'}(function ($rec) {
            return true;
        }), 'has'.$method.'ThatPasses');
        $this->assertFalse($handler->{'has'.$method.'ThatMatches'}('/test\w+/'));
        $this->assertFalse($handler->{'has'.$method.'Records'}(), 'has'.$method.'Records');
        $handler->log($level, 'test'.$method);

        $this->assertFalse($handler->{'has'.$method}('bar'), 'has'.$method);
        $this->assertTrue($handler->hasRecords($level));
        $this->assertTrue($handler->hasRecord($record, $level));
        $this->assertTrue($handler->{'has'.$method}($record), 'has'.$method);
        $this->assertTrue($handler->{'has'.$method}('test'.$method), 'has'.$method);
        $this->assertTrue($handler->{'has'.$method.'ThatContains'}('test'), 'has'.$method.'ThatContains');
        $this->assertTrue($handler->{'has'.$method.'ThatPasses'}(function ($rec) {
            return true;
        }), 'has'.$method.'ThatPasses');
        $this->assertTrue($handler->{'has'.$method.'ThatMatches'}('/test\w+/'));
        $this->assertTrue($handler->{'has'.$method.'Records'}(), 'has'.$method.'Records');

        $records = $handler->records;
        $this->assertEquals([$record], $records);
    }

    public function testHandlerAssertEmptyContext()
    {
        $logger = new TestLogger();
        $this->assertFalse($logger->hasWarning([
            'message' => 'test',
            'context' => [],
        ]));

        $logger->warning('test');

        $this->assertTrue($logger->hasWarning([
            'message' => 'test',
            'context' => [],
        ]));
        $this->assertFalse($logger->hasWarning([
            'message' => 'test',
            'context' => [
                'foo' => 'bar',
            ],
        ]));
    }

    public function testHandlerAssertNonEmptyContext()
    {
        $handler = new TestLogger;
        $this->assertFalse($handler->hasWarning([
            'message' => 'test',
            'context' => [
                'foo' => 'bar',
            ],
        ]));

        $handler->warning('test', ['foo' => 'bar']);

        $this->assertTrue($handler->hasWarning([
            'message' => 'test',
            'context' => [
                'foo' => 'bar',
            ],
        ]));
        $this->assertFalse($handler->hasWarning([
            'message' => 'test',
            'context' => [],
        ]));
    }

    public function methodProvider()
    {
        return [
            ['Emergency'],
            ['Alert'],
            ['Critical'],
            ['Error'],
            ['Warning'],
            ['Info'],
            ['Notice'],
            ['Debug'],
        ];
    }
}
