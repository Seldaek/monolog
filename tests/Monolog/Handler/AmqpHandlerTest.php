<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Test\TestCase;
use Monolog\Level;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * @covers Monolog\Handler\RotatingFileHandler
 */
class AmqpHandlerTest extends TestCase
{
    public function testHandleAmqpExt()
    {
        if (!class_exists('AMQPConnection') || !class_exists('AMQPExchange')) {
            $this->markTestSkipped("amqp-php not installed");
        }

        if (!class_exists('AMQPChannel')) {
            $this->markTestSkipped("Please update AMQP to version >= 1.0");
        }

        $messages = [];

        $exchange = $this->getMockBuilder('AMQPExchange')
            ->onlyMethods(['publish', 'setName'])
            ->disableOriginalConstructor()
            ->getMock();

        $exchange->expects($this->any())
            ->method('publish')
            ->will($this->returnCallback(function ($message, $routing_key, $flags = 0, $attributes = []) use (&$messages) {
                $messages[] = [$message, $routing_key, $flags, $attributes];
            }))
        ;

        $handler = new AmqpHandler($exchange);

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $expected = [
            [
                'message' => 'test',
                'context' => [
                    'data' => [],
                    'foo' => 34,
                ],
                'level' => 300,
                'level_name' => 'WARNING',
                'channel' => 'test',
                'extra' => [],
            ],
            'warning.test',
            0,
            [
                'delivery_mode' => 2,
                'content_type' => 'application/json',
            ],
        ];

        $handler->handle($record);

        $this->assertCount(1, $messages);
        $messages[0][0] = json_decode($messages[0][0], true);
        unset($messages[0][0]['datetime']);
        $this->assertEquals($expected, $messages[0]);
    }

    public function testHandlePhpAmqpLib()
    {
        if (!class_exists('PhpAmqpLib\Channel\AMQPChannel')) {
            $this->markTestSkipped("php-amqplib not installed");
        }

        $messages = [];

        $methodsToMock = ['basic_publish'];
        if (method_exists('PhpAmqpLib\Channel\AMQPChannel', '__destruct')) {
            $methodsToMock[] = '__destruct';
        }

        $exchange = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
            ->onlyMethods($methodsToMock)
            ->disableOriginalConstructor()
            ->getMock();

        $exchange->expects($this->any())
            ->method('basic_publish')
            ->will($this->returnCallback(function (AMQPMessage $msg, $exchange = "", $routing_key = "", $mandatory = false, $immediate = false, $ticket = null) use (&$messages) {
                $messages[] = [$msg, $exchange, $routing_key, $mandatory, $immediate, $ticket];
            }))
        ;

        $handler = new AmqpHandler($exchange, 'log');

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $expected = [
            [
                'message' => 'test',
                'context' => [
                    'data' => [],
                    'foo' => 34,
                ],
                'level' => 300,
                'level_name' => 'WARNING',
                'channel' => 'test',
                'extra' => [],
            ],
            'log',
            'warning.test',
            false,
            false,
            null,
            [
                'delivery_mode' => 2,
                'content_type' => 'application/json',
            ],
        ];

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
