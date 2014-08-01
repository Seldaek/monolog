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

/**
 * Logs to a Redis key using lpush
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $redis = new RedisHandler(new Predis\Client("tcp://localhost:6379"), "logs", "prod");
 *   $log->pushHandler($redis);
 *
 * @author cd <cleardevice@gmail.com>
 */
class RedisBackwardHandler extends RedisHandler
{
    protected function write(array $record)
    {
        $this->redisClient->lpush($this->redisKey, $record["formatted"]);
    }
}
