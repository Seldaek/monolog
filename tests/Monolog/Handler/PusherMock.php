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

class PusherMock
{
    protected $key;
    protected $secret;
    protected $app_id;
    protected $debug;

    public function __construct($key, $secret, $app_id, $debug = true)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->app_id = $app_id;
        $this->debug = $debug;
    }

    public function trigger($channel, $event, $record)
    {
        return true;
    }
}
