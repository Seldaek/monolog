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

    public function testThatDSNOrSocketHaveToBePassed()
    {
        $this->setExpectedException('InvalidArgumentException');
        new ZmqHandler();
    }

    public function testPassingDSNCreatesSocket()
    {
        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $reflectionClass = new \ReflectionClass('\Monolog\Handler\ZmqHandler');
        $property = $reflectionClass->getProperty('socket');
        $property->setAccessible(true);

        $handler = new ZmqHandler('tcp://localhost:5555', \ZMQ::SOCKET_PUB);
        $handler->handle($record);

        $createdSocket = $property->getValue($handler);
        $this->assertTrue($createdSocket instanceof \ZMQSocket, 'Socket is created');

        $objHashPrev = spl_object_hash($createdSocket);
        $handler->handle($record);
        $this->assertEquals($objHashPrev, spl_object_hash($property->getValue($handler)), 'Socket is created only once and reused');

    }

    public function testThatAPassedSocketIsUsed()
    {
        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $socket = $this->getMockBuilder('ZMQSocket')
            ->disableOriginalConstructor()
            ->getMock();

        $socket->expects($this->once())
            ->method('send')
            ->with(json_encode($record));

        $handler = new ZmqHandler(null, null, null, $socket);
        $handler->handle($record);
    }
}
