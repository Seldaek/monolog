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

class FingersCrossedHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleBuffers()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getMessage(Logger::DEBUG));
        $handler->handle($this->getMessage(Logger::INFO));
        $this->assertFalse($test->hasDebugMessages());
        $this->assertFalse($test->hasInfoMessages());
        $handler->handle($this->getMessage(Logger::WARNING));
        $this->assertTrue($test->hasInfoMessages());
        $this->assertTrue(count($test->getMessages()) === 3);
    }

    public function testHandleStopsBufferingAfterTrigger()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getMessage(Logger::WARNING));
        $handler->handle($this->getMessage(Logger::DEBUG));
        $this->assertTrue($test->hasWarningMessages());
        $this->assertTrue($test->hasDebugMessages());
    }

    public function testHandleBufferLimit()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Logger::WARNING, 2);
        $handler->handle($this->getMessage(Logger::DEBUG));
        $handler->handle($this->getMessage(Logger::DEBUG));
        $handler->handle($this->getMessage(Logger::INFO));
        $handler->handle($this->getMessage(Logger::WARNING));
        $this->assertTrue($test->hasWarningMessages());
        $this->assertTrue($test->hasInfoMessages());
        $this->assertFalse($test->hasDebugMessages());
    }

    protected function getMessage($level = Logger::WARNING)
    {
        return array(
            'level' => $level,
            'level_name' => 'WARNING',
            'channel' => 'log',
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array(),
        );
    }
}
