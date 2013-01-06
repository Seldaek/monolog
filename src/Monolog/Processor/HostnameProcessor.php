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
 * Injects environment's hostname in all records
 *
 * @author Clay Loveless <clay@php.net>
 */
class HostnameProcessor
{
    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'hostname' => php_uname('n'),
            )
        );

        return $record;
    }
}
