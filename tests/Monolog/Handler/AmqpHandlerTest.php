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
class RotatingFileHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('AMQPConnection') || !class_exists('AMQPExchange')) {
            $this->markTestSkipped("amqp-php not installed");
        }

        if (!class_exists('AMQPChannel')) {
            throw new \Exception(' Please update AMQP to version >= 1');
        }

        require_once __DIR__ . '/AmqpMocks.php';
    }

    public function testWrite()
    {
//        $handler = new AmqpHandler($this->getMockAMQPConnection(), 'log', 'monolog');
    }

    public function getMockAMQPConnection() {
        return new MockAMQPConnection();
    }

    public function tearDown()
    {
        foreach (glob(__DIR__.'/Fixtures/*.rot') as $file) {
            unlink($file);
        }
    }
}