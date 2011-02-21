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
        $topHandler = new TestHandler(Logger::DEBUG, true);
        $bottomHandler = new TestHandler(Logger::INFO);
        $topHandler->setParent($bottomHandler);
        $this->assertTrue($topHandler->handle($this->getMessage()));
        $this->assertTrue($bottomHandler->hasWarningMessages());
    }

    public function testHandleNotBubbling()
    {
        $topHandler = new TestHandler(Logger::DEBUG);
        $bottomHandler = new TestHandler(Logger::INFO);
        $topHandler->setParent($bottomHandler);
        $this->assertTrue($topHandler->handle($this->getMessage()));
        $this->assertFalse($bottomHandler->hasWarningMessages());
    }

    public function testGetHandlerReturnEarly()
    {
        $topHandler = new TestHandler(Logger::DEBUG);
        $bottomHandler = new TestHandler(Logger::INFO);
        $topHandler->setParent($bottomHandler);
        $this->assertEquals($topHandler, $topHandler->getHandler($this->getMessage()));
    }

    public function testGetHandlerReturnsParent()
    {
        $topHandler = new TestHandler(Logger::ERROR);
        $bottomHandler = new TestHandler(Logger::INFO);
        $topHandler->setParent($bottomHandler);
        $this->assertEquals($bottomHandler, $topHandler->getHandler($this->getMessage()));
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
