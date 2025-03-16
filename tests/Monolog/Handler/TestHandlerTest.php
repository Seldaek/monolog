<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers Monolog\Handler\TestHandler
 */
class TestHandlerTest extends \Monolog\Test\MonologTestCase
{
    #[DataProvider('methodProvider')]
    public function testHandler($method, Level $level)
    {
        $handler = new TestHandler;
        $record = $this->getRecord($level, 'test'.$method);
        $this->assertFalse($handler->hasRecords($level));
        $this->assertFalse($handler->hasRecord($record->message, $level));
        $this->assertFalse($handler->{'has'.$method}($record->message), 'has'.$method);
        $this->assertFalse($handler->{'has'.$method.'ThatContains'}('test'), 'has'.$method.'ThatContains');
        $this->assertFalse($handler->{'has'.$method.'ThatPasses'}(function ($rec) {
            return true;
        }), 'has'.$method.'ThatPasses');
        $this->assertFalse($handler->{'has'.$method.'ThatMatches'}('/test\w+/'));
        $this->assertFalse($handler->{'has'.$method.'Records'}(), 'has'.$method.'Records');
        $handler->handle($record);

        $this->assertFalse($handler->{'has'.$method}('bar'), 'has'.$method);
        $this->assertTrue($handler->hasRecords($level));
        $this->assertTrue($handler->hasRecord($record->message, $level));
        $this->assertTrue($handler->{'has'.$method}($record->message), 'has'.$method);
        $this->assertTrue($handler->{'has'.$method}('test'.$method), 'has'.$method);
        $this->assertTrue($handler->{'has'.$method.'ThatContains'}('test'), 'has'.$method.'ThatContains');
        $this->assertTrue($handler->{'has'.$method.'ThatPasses'}(function ($rec) {
            return true;
        }), 'has'.$method.'ThatPasses');
        $this->assertTrue($handler->{'has'.$method.'ThatMatches'}('/test\w+/'));
        $this->assertTrue($handler->{'has'.$method.'Records'}(), 'has'.$method.'Records');

        $records = $handler->getRecords();
        $records[0]->formatted = null;
        $this->assertEquals([$record], $records);
    }

    public function testHandlerAssertEmptyContext()
    {
        $handler = new TestHandler;
        $record  = $this->getRecord(Level::Warning, 'test', []);
        $this->assertFalse($handler->hasWarning([
            'message' => 'test',
            'context' => [],
        ]));

        $handler->handle($record);

        $this->assertTrue($handler->hasWarning([
            'message' => 'test',
            'context' => [],
        ]));
        $this->assertFalse($handler->hasWarning([
            'message' => 'test',
            'context' => [
                'foo' => 'bar',
            ],
        ]));
    }

    public function testHandlerAssertNonEmptyContext()
    {
        $handler = new TestHandler;
        $record  = $this->getRecord(Level::Warning, 'test', ['foo' => 'bar']);
        $this->assertFalse($handler->hasWarning([
            'message' => 'test',
            'context' => [
                'foo' => 'bar',
            ],
        ]));

        $handler->handle($record);

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

    public static function methodProvider()
    {
        return [
            ['Emergency', Level::Emergency],
            ['Alert'    , Level::Alert],
            ['Critical' , Level::Critical],
            ['Error'    , Level::Error],
            ['Warning'  , Level::Warning],
            ['Info'     , Level::Info],
            ['Notice'   , Level::Notice],
            ['Debug'    , Level::Debug],
        ];
    }
}
