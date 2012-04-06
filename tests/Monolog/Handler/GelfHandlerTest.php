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
use Gelf\MessagePublisher;

class GelfHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists("Gelf\MessagePublisher"))
        {
            $this->markTestSkipped("https://github.com/mlehner/gelf-php not installed");
        }
    }

    /**
     * @covers Monolog\Handler\GelfHandler::__construct
     */
    public function testConstruct()
    {
        $handler = new GelfHandler($this->getMessagePublisher());
        $this->assertInstanceOf('Monolog\Handler\GelfHandler', $handler);
    }

    protected function getMessagePublisher()
    {
        return new MessagePublisher('localhost');
    }

    public function testStuff()
    {
        $handler = new GelfHandler($this->getMessagePublisher());
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::WARNING));
    }
}
