<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

/**
 * Adds value of getmypid into records
 *
 * @author Andreas Hörnicke
 */
class ProcessIdProcessor
{
    private static $pid;

    public function __construct()
    {
        if (null === self::$pid) {
            self::$pid = getmypid();
        }
    }

    /**
     * @param  array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra']['process_id'] = self::$pid;

        return $record;
    }
}
