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
use Monolog\Formatter\LineFormatter;

class RedisHandlerTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowExceptionForInvalidRedis()
    {
        new RedisHandler(new \stdClass(), 'key');
    }

    public function testConstructorShouldWorkWithPredis()
    {
        $redis = $this->getMock('Predis\Client');
        $this->assertInstanceof('Monolog\Handler\RedisHandler', new RedisHandler($redis, 'key'));
    }

    public function testConstructorShouldWorkWithRedis()
    {
        $redis = $this->getMock('Redis');
        $this->assertInstanceof('Monolog\Handler\RedisHandler', new RedisHandler($redis, 'key'));
    }

    public function testPredisHandle()
    {
        $redis = $this->getMock('Predis\Client', array('rpush'));

        // Predis\Client uses rpush
        $redis->expects($this->once())
            ->method('rpush')
            ->with('key', 'test');

        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $handler = new RedisHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testRedisHandle()
    {
        $redis = $this->getMock('Redis', array('rpush'));

        // Redis uses rPush
        $redis->expects($this->once())
            ->method('rPush')
            ->with('key', 'test');

        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $handler = new RedisHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }
}
