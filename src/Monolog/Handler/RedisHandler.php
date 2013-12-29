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

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Predis\Client;

/**
 * Logs to a Redis key using rpush
 * usage example:
 *   $log = new Logger('application');
 *   $redis = new RedisHandler(new Predis\Client("tcp://localhost:6379"), "logs", "prod");
 *   $log->pushHandler($redis);
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class RedisHandler
    extends AbstractProcessingHandler
{
    /**
     * @var \Predis\Client|\Redis
     */
    private $redisClient;

    /**
     * @var string
     */
    private $redisKey;

    /**
     * @param \Redis|\Redis\Client $redis
     * @param bool                 $key
     * @param int                  $level
     * @param bool                 $bubble
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        $redis,
        $key,
        $level = Logger::DEBUG,
        $bubble = true
    ) {
        if (!(($redis instanceof Client) || ($redis instanceof \Redis))) {
            throw new \InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->redisClient = $redis;
        $this->redisKey    = $key;

        parent::__construct(
            $level,
            $bubble
        );
    }

    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        $this->redisClient->rpush(
            $this->redisKey,
            $record["formatted"]
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter();
    }
}
