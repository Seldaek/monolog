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
use Monolog\Handler\MockAMQPExchange;

/**
 * @covers Monolog\Handler\RotatingFileHandler
 */
class AmqpHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('AMQPConnection') || !class_exists('AMQPExchange')) {
            $this->markTestSkipped("amqp-php not installed");
        }

        if (!class_exists('AMQPChannel')) {
            $this->markTestSkipped("Please update AMQP to version >= 1");
        }
    }

    public function testHandle()
    {
        $exchange = $this->getExchange();

        $handler = new AmqpHandler($exchange, 'log');

        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $handler->handle($record);
    }

    protected function getExchange()
    {
        /* sorry, but PHP bug in zend_object_store_get_object segfaults
        php where using mocks on AMQP classes. should be fixed someday,
        but now it's time for some shitcode (see below)
        $exchange = $this->getMockBuilder('\AMQPExchange')
            ->setConstructorArgs(array($this->getMock('\AMQPChannel')))
            ->setMethods(array('setName'))
            ->getMock();

        $exchange->expects($this->any())
            ->method('setName')
            ->will($this->returnValue(true));
        */
        return new MockAMQPExchange();
    }
}