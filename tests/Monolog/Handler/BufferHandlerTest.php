<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;

class BufferHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleBuffers()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->close();
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue(count($test->getRecords()) === 2);
    }

    public function testDestructPropagatesRecords()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->handle($this->getRecord(Logger::DEBUG));
        unset ($handler);
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
    }

    public function testHandleBufferLimit()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 2);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    protected function getRecord($level = Logger::WARNING)
    {
        return array(
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'log',
            'Record' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array(),
        );
    }
}
