<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme;

class Tester
{
    public function test($handler, $record)
    {
        $handler->handle($record);
    }
}

function tester($handler, $record)
{
    $handler->handle($record);
}

namespace Monolog\Processor;

use Monolog\Level;
use Monolog\Handler\TestHandler;

class IntrospectionProcessorTest extends \Monolog\Test\MonologTestCase
{
    public function getHandler()
    {
        $processor = new IntrospectionProcessor();
        $handler = new TestHandler();
        $handler->pushProcessor($processor);

        return $handler;
    }

    public function testProcessorFromClass()
    {
        $handler = $this->getHandler();
        $tester = new \Acme\Tester;
        $tester->test($handler, $this->getRecord());
        list($record) = $handler->getRecords();
        $this->assertEquals(__FILE__, $record->extra['file']);
        $this->assertEquals(18, $record->extra['line']);
        $this->assertEquals('Acme\Tester', $record->extra['class']);
        $this->assertEquals('test', $record->extra['function']);
    }

    public function testProcessorFromFunc()
    {
        $handler = $this->getHandler();
        \Acme\tester($handler, $this->getRecord());
        list($record) = $handler->getRecords();
        $this->assertEquals(__FILE__, $record->extra['file']);
        $this->assertEquals(24, $record->extra['line']);
        $this->assertEquals(null, $record->extra['class']);
        $this->assertEquals('Acme\tester', $record->extra['function']);
    }

    public function testLevelTooLow()
    {
        $input = $this->getRecord(Level::Debug);

        $expected = clone $input;

        $processor = new IntrospectionProcessor(Level::Critical);
        $actual = $processor($input);

        $this->assertEquals($expected, $actual);
    }

    public function testLevelEqual()
    {
        $input = $this->getRecord(Level::Critical);

        $expected = clone $input;
        $expected['extra'] = [
            'file' => null,
            'line' => null,
            'class' => 'PHPUnit\Framework\TestCase',
            'function' => 'runTest',
            'callType' => '->',
        ];

        $processor = new IntrospectionProcessor(Level::Critical);
        $actual = $processor($input);

        $this->assertEquals($expected, $actual);
    }

    public function testLevelHigher()
    {
        $input = $this->getRecord(Level::Emergency);

        $expected = clone $input;
        $expected['extra'] = [
            'file' => null,
            'line' => null,
            'class' => 'PHPUnit\Framework\TestCase',
            'function' => 'runTest',
            'callType' => '->',
        ];

        $processor = new IntrospectionProcessor(Level::Critical);
        $actual = $processor($input);

        $this->assertEquals($expected, $actual);
    }
}
