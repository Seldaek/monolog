<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) cd <cleardevice@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class RedisBackwardHandlerTest extends RedisHandlerTest
{
    public function testPredisHandle()
    {
        $redis = $this->getMock('Predis\Client', array('lpush'));

        // Predis\Client uses lpush
        $redis->expects($this->once())
            ->method('lpush')
            ->with('key', 'test');

        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $handler = new RedisBackwardHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testRedisHandle()
    {
        $redis = $this->getMock('Redis', array('lpush'));

        // Redis uses lPush
        $redis->expects($this->once())
            ->method('lPush')
            ->with('key', 'test');

        $record = $this->getRecord(Logger::WARNING, 'test', array('data' => new \stdClass, 'foo' => 34));

        $handler = new RedisBackwardHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }
}
