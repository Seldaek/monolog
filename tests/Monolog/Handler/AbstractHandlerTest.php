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

class AbstractHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandle()
    {
        $handler = new TestHandler();
        $this->assertTrue($handler->handle($this->getMessage()));
    }

    public function testHandleLowerLevelMessage()
    {
        $handler = new TestHandler(Logger::WARNING);
        $this->assertFalse($handler->handle($this->getMessage(Logger::DEBUG)));
    }

    public function testHandleBubbling()
    {
        $handler = new TestHandler(Logger::DEBUG, true);
        $this->assertFalse($handler->handle($this->getMessage()));
    }

    public function testHandleNotBubbling()
    {
        $handler = new TestHandler(Logger::DEBUG);
        $this->assertTrue($handler->handle($this->getMessage()));
    }

    public function testIsHandling()
    {
        $handler = new TestHandler(Logger::WARNING);
        $this->assertTrue($handler->handle($this->getMessage()));
        $this->assertFalse($handler->handle($this->getMessage(Logger::DEBUG)));
    }

    protected function getMessage($level = Logger::WARNING)
    {
        return array(
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'log',
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array(),
        );
    }
}
