<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\SocketHandler;

use Monolog\Handler\SocketHandler\Exception\ConnectionException;

/**
 * Same as Socket but uses pfsockopen() instead allowing the connection to be reused in other requests.
 * 
 * @see http://php.net/manual/en/function.pfsockopen.php
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */
class PersistentSocket extends Socket
{
    protected function createSocketResource()
    {
        @$resource = pfsockopen($this->connectionString, -1, $errno, $errstr, $this->connectionTimeout);
        if (!$resource) {
            throw new ConnectionException("Failed connecting to $this->connectionString ($errno: $errstr)");
        }
        $this->resource = $resource;
    }
}
