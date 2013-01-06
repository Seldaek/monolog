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
    protected $appId;
    protected $debug;

    public function __construct($key, $secret, $appId, $debug = true)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->appId = $appId;
        $this->debug = $debug;
    }

    public function trigger($channel, $event, $record)
    {
        return true;
    }
}
