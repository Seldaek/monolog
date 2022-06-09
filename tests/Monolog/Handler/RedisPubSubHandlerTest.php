<?php

declare(strict_types=1);

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
use Monolog\Formatter\LineFormatter;

class RedisPubSubHandlerTest extends TestCase
{
    public function testConstructorShouldWorkWithPredis()
    {
        $redis = $this->createMock('Predis\Client');
        $this->assertInstanceof('Monolog\Handler\RedisPubSubHandler', new RedisPubSubHandler($redis, 'key'));
    }

    public function testConstructorShouldWorkWithRedis()
    {
        if (!class_exists('Redis')) {
            $this->markTestSkipped('The redis ext is required to run this test');
        }

        $redis = $this->createMock('Redis');
        $this->assertInstanceof('Monolog\Handler\RedisPubSubHandler', new RedisPubSubHandler($redis, 'key'));
    }

    public function testPredisHandle()
    {
        $redis = $this->getMockBuilder('Predis\Client')->getMock();
        $redis->expects($this->atLeastOnce())
            ->method('__call')
            ->with(self::equalTo('publish'), self::equalTo(['key', 'test']));

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new RedisPubSubHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testRedisHandle()
    {
        if (!class_exists('Redis')) {
            $this->markTestSkipped('The redis ext is required to run this test');
        }

        $redis = $this->createPartialMock('Redis', ['publish']);

        $redis->expects($this->once())
            ->method('publish')
            ->with('key', 'test');

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new RedisPubSubHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }
}
