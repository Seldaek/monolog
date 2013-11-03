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
 * Adds PID to a log record
 *
 * @author Andreas Hörnicke
 * @author Andrey Ryaguzov <dev.aeryaguzov@gmail.com>
 */
class PidProcessor
{
    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra']['pid'] = getmypid();

        return $record;
    }
}
