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
            $this->markTestSkipped("Please update AMQP to version >= 1.0");
        }
    }

    public function testHandle()
    {
        $exchange = $this->getExchange();

        $handler = new AmqpHandler($exchange, 'log');

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
            'warn.test',
            0,
            array(
                'delivery_mode' => 2,
                'Content-type' => 'application/json'
            )
        );

        $handler->handle($record);

        $messages = $exchange->getMessages();
        $this->assertCount(1, $messages);
        $messages[0][0] = json_decode($messages[0][0], true);
        unset($messages[0][0]['datetime']);
        $this->assertEquals($expected, $messages[0]);
    }

    protected function getExchange()
    {
        return new AmqpExchangeMock();
    }
}
