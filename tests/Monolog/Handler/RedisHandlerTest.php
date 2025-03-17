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

use Monolog\Level;
use Monolog\Formatter\LineFormatter;

class RedisHandlerTest extends \Monolog\Test\MonologTestCase
{
    public function testConstructorShouldWorkWithPredis()
    {
        $redis = $this->createMock('Predis\Client');
        $this->assertInstanceof('Monolog\Handler\RedisHandler', new RedisHandler($redis, 'key'));
    }

    public function testConstructorShouldWorkWithRedis()
    {
        if (!class_exists('Redis')) {
            $this->markTestSkipped('The redis ext is required to run this test');
        }

        $redis = $this->createMock('Redis');
        $this->assertInstanceof('Monolog\Handler\RedisHandler', new RedisHandler($redis, 'key'));
    }

    public function testPredisHandle()
    {
        $redis = $this->getMockBuilder('Predis\Client')->getMock();
        $redis->expects($this->atLeastOnce())
            ->method('__call')
            ->with(self::equalTo('rpush'), self::equalTo(['key', 'test']));

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $handler = new RedisHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testRedisHandle()
    {
        if (!class_exists('Redis')) {
            $this->markTestSkipped('The redis ext is required to run this test');
        }

        $redis = $this->createPartialMock('Redis', ['rPush']);

        // Redis uses rPush
        $redis->expects($this->once())
            ->method('rPush')
            ->with('key', 'test');

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $handler = new RedisHandler($redis, 'key');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testRedisHandleCapped()
    {
        if (!class_exists('Redis')) {
            $this->markTestSkipped('The redis ext is required to run this test');
        }

        $redis = $this->createPartialMock('Redis', ['multi', 'rPush', 'lTrim', 'exec']);

        // Redis uses multi
        $redis->expects($this->once())
            ->method('multi')
            ->willReturnSelf();

        $redis->expects($this->once())
            ->method('rPush')
            ->willReturnSelf();

        $redis->expects($this->once())
            ->method('lTrim')
            ->willReturnSelf();

        $redis->expects($this->once())
            ->method('exec')
            ->willReturnSelf();

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $handler = new RedisHandler($redis, 'key', Level::Debug, true, 10);
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testPredisHandleCapped()
    {
        $redis = new class extends \Predis\Client {
            public array $testResults = [];

            public function rpush(...$args)
            {
                $this->testResults[] = ['rpush', ...$args];

                return $this;
            }

            public function ltrim(...$args)
            {
                $this->testResults[] = ['ltrim', ...$args];

                return $this;
            }

            public function transaction(...$args)
            {
                $this->testResults[] = ['transaction start'];

                return ($args[0])($this);
            }
        };

        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $handler = new RedisHandler($redis, 'key', Level::Debug, true, 10);
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);

        self::assertsame([
            ['transaction start'],
            ['rpush', 'key', 'test'],
            ['ltrim', 'key', -10, -1],
        ], $redis->testResults);
    }
}
