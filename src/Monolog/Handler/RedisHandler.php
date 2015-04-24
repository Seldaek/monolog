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

/**
 * Logs to a Redis key using rpush
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $redis = new RedisHandler(new Predis\Client("tcp://localhost:6379"), "logs", "prod");
 *   $log->pushHandler($redis);
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class RedisHandler extends AbstractProcessingHandler
{
    private $redisClient;
    private $redisKey;

    /**
     * @param \Predis\Client|\Redis $redis  The redis instance
     * @param string                $key    The key name to push records to
     * @param integer               $level  The minimum logging level at which this handler will be triggered
     * @param boolean               $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($redis, $key, $level = Logger::DEBUG, $bubble = true)
    {
        if (!(($redis instanceof \Predis\Client) || ($redis instanceof \Redis))) {
            throw new \InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->redisClient = $redis;
        $this->redisKey = $key;

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        $this->redisClient->rpush($this->redisKey, $record["formatted"]);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter();
    }
}
