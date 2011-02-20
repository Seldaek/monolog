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

class NullHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandle()
    {
        $handler = new NullHandler();
        $this->assertTrue($handler->handle($this->getMessage()));
    }

    public function testHandleLowerLevelMessage()
    {
        $handler = new NullHandler(Logger::WARNING);
        $this->assertFalse($handler->handle($this->getMessage(Logger::DEBUG)));
    }

    public function testHandleBubbling()
    {
        $handler = new NullHandler(Logger::DEBUG, true);
        $this->assertFalse($handler->handle($this->getMessage()));
    }

    /**
     * No-op test for coverage
     */
    public function testWrite()
    {
        $handler = new NullHandler();
        $handler->write($this->getMessage());
    }

    protected function getMessage($level = Logger::WARNING)
    {
        return array(
            'level' => $level,
        );
    }
}