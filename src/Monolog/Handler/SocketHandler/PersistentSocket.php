<?php
/**
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */

namespace Monolog\Handler\SocketHandler;

use Monolog\Handler\SocketHandler\Exception\ConnectionException;

/**
 * Same as Socket but uses pfsockopen() instead allowing the connection to be reused in other requests.
 * 
 * @see http://php.net/manual/en/function.pfsockopen.php
 */
class PersistentSocket extends Socket
{
    protected function createSocketResource() {
        @$resource = pfsockopen($this->connectionString, -1, $errno, $errstr, $this->connectionTimeout);
        if (!$resource) {
            throw new ConnectionException("Failed connecting to $this->connectionString ($errno: $errstr)");
        }
        $this->resource = $resource;
    }
}
