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
 * Injects url/method and remote IP of the current web request in all records
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class WebProcessor
{
    /**
     * @param array $record
     * @param HandlerInterface $handler
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'url' => $_SERVER['REQUEST_URI'],
                'ip' => $_SERVER['REMOTE_ADDR'],
                'method' => $_SERVER['REQUEST_METHOD'],
            )
        );

        return $record;
    }
}
