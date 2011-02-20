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

class WebProcessor
{
    public function __invoke($message, $handler)
    {
        $message['extra'] = array_merge(
            $message['extra'],
            array(
                'url' => $_SERVER['REQUEST_URI'],
                'ip' => $_SERVER['REMOTE_ADDR'],
                'method' => $_SERVER['REQUEST_METHOD'],
            )
        );
        return $message;
    }
}
