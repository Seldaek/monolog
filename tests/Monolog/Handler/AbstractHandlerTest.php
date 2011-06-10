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

use Monolog\TestCase;
use Monolog\Logger;

class AbstractHandlerTest extends TestCase
{
    public function testHandleLowerLevelMessage()
    {
        $handler = new TestHandler(Logger::WARNING, true);
        $this->assertFalse($handler->handle($this->getRecord(Logger::DEBUG)));
    }

    public function testHandleBubbling()
    {
        $handler = new TestHandler(Logger::DEBUG, true);
        $this->assertFalse($handler->handle($this->getRecord()));
    }

    public function testHandleNotBubbling()
    {
        $handler = new TestHandler(Logger::DEBUG, false);
        $this->assertTrue($handler->handle($this->getRecord()));
    }

    public function testIsHandling()
    {
        $handler = new TestHandler(Logger::WARNING, false);
        $this->assertTrue($handler->handle($this->getRecord()));
        $this->assertFalse($handler->handle($this->getRecord(Logger::DEBUG)));
    }
}
