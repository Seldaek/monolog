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

class ZmqHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('ZMQContext')) {
            $this->markTestSkipped("php-zmq not installed");
        }
    }

    public function testHandle()
    {
        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $socket = $this->getMockBuilder('ZMQSocket')
            ->disableOriginalConstructor()
            ->getMock();

        $socket->expects($this->once())
            ->method('send')
            ->with(json_encode($record));

        $handler = new ZmqHandler($socket, 'log');
        $handler->handle($record);
    }

}
