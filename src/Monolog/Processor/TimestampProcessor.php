<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Giuseppe Iannello <giuseppe.iannello@brokenloop.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\Logger;

/**
 * Injects unix timestamp with microseconds in all records
 * This timestamp is _not_ the same as the datetime field in the records,
 * due to limits of the DateTime PHP object. It can be considered a pretty
 * good approximation, as the Processor code is executed shortly after the
 * message is generated.
 *
 * @author Giuseppe Iannello <giuseppe.iannello@brokenloop.net>
 */
class TimestampProcessor
{
    private $level;
    private static $cache;

    public function __construct($level = Logger::DEBUG)
    {
        $this->level = Logger::toMonologLevel($level);
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        // return if the level is not high enough
        if ($record['level'] < $this->level) {
            return $record;
        }

        $record['extra']['timestamp'] = sprintf('%.6F', microtime(true));

        return $record;
    }
}
