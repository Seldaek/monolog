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
use PhpAmqpLib\Message\AMQPMessage;

/**
 * @covers Monolog\Handler\RotatingFileHandler
 */
class PhpAmqpLibHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('PhpAmqpLib\Connection\AMQPConnection')) {
            $this->markTestSkipped("php-amqplib not installed");
        }
    }

    public function testHandle()
    {
        $messages = array();

        $exchange = $this->getMock('PhpAmqpLib\Channel\AMQPChannel', array('basic_publish', '__destruct'), array(), '', false);

        $exchange->expects($this->any())
            ->method('basic_publish')
            ->will($this->returnCallback(function (AMQPMessage $msg, $exchange = "", $routing_key = "", $mandatory = false, $immediate = false, $ticket = null) use (&$messages) {
                $messages[] = array($msg, $exchange, $routing_key, $mandatory, $immediate, $ticket);
            }))
        ;

        $handler = new PhpAmqpLibHandler($exchange, 'log');

        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $expected = array(
            array(
                'message' => 'test',
                'context' => array(
                    'data' => array(),
                    'foo' => 34,
                ),
                'level' => 300,
                'level_name' => 'WARNING',
                'channel' => 'test',
                'extra' => array(),
            ),
            'log',
            'warn.test',
            false,
            false,
            null,
            array(
                'delivery_mode' => 2,
                'content_type' => 'application/json'
            )
        );

        $handler->handle($record);

        $this->assertCount(1, $messages);

        /* @var $msg AMQPMessage */
        $msg = $messages[0][0];
        $messages[0][0] = json_decode($msg->body, true);
        $messages[0][] = $msg->get_properties();
        unset($messages[0][0]['datetime']);

        $this->assertEquals($expected, $messages[0]);
    }
}
