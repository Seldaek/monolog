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
use Monolog\Handler\SocketHandler\Exception\WriteToSocketException;

class MockSocket extends Socket
{
    private $connectTimeoutMock = 0;
    private $timeoutMock = 0;
    
    
    public function __construct($connectionString)
    {
        if (is_resource($connectionString)) {
            $this->resource = $connectionString;
        } else {
            $this->connectionString = $connectionString;
        }
    }
    
    public function setFailConnectionTimeout($seconds)
    {
        $this->connectTimeoutMock = (int)$seconds;
    }
    
    public function setFailTimeout($seconds)
    {
        $this->timeoutMock = (int)$seconds;
    }
    
    protected function createSocketResource()
    {
        if ($this->connectTimeoutMock > 0) {
            throw new ConnectionException("Mocked connection timeout");
        }
        $this->resource = fopen('php://memory', '+a');
    }
    
    protected function writeToSocket($data) {
        if ($this->timeoutMock > 0) {
            throw new WriteToSocketException("Mocked write timeout");
        }
        return parent::writeToSocket($data);
    }
    
    protected function setSocketTimeout()
    {
        // php://memory does not support this
    }
    
    public function getResource() {
        return $this->resource;
    }
}
