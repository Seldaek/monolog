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

use Monolog\Logger;
use Monolog\Handler\SocketHandler\Exception\ConnectionException;
use Monolog\Handler\SocketHandler\Exception\WriteToSocketException;


/**
 * Stores to any socket - uses fsockopen() or pfsockopen().
 * 
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 * @see http://php.net/manual/en/function.fsockopen.php
 */
class SocketHandler extends AbstractProcessingHandler
{
    private $connectionString;
    private $connectionTimeout;
    private $resource;
    private $timeout = 0;
    private $persistent = false;
    
    /**
     * @param string $connectionString
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($connectionString, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->connectionString = $connectionString;
        $this->connectionTimeout = (float)ini_get('default_socket_timeout');
    }
   
    /**
     * Connect (if necessary) and write to the socket
     * 
     * @throws Monolog\Handler\SocketHandler\Exception\ConnectionException
     * @throws Monolog\Handler\SocketHandler\Exception\WriteToSocketException
     * @param string $string
     */
    public function write(array $record)
    {
        $this->connectIfNotConnected();
        $this->writeToSocket((string) $record['formatted']);
    }
    
    /**
     * We will not close a PersistentSocket instance so it can be reused in other requests.
     */
    public function close()
    {
        if ($this->isPersistent()) {
            return;
        }
        $this->closeSocket();
    }
    
    public function closeSocket()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
            $this->resource = null;
        }
    }
    
    public function setPersistent($boolean)
    {
        $this->persistent = (boolean)$boolean;
    }
    
    /**
     * Set connection timeout.  Only has effect before we connect.
     * 
     * @see http://php.net/manual/en/function.fsockopen.php
     * @param integer $seconds 
     */
    public function setConnectionTimeout($seconds)
    {
        $this->validateTimeout($seconds);
        $this->connectionTimeout = (float)$seconds;
    }
    
    /**
     * Set write timeout. Only has effect before we connect.
     * 
     * @see http://php.net/manual/en/function.stream-set-timeout.php
     * @param type $seconds 
     */
    public function setTimeout($seconds)
    {
        $this->validateTimeout($seconds);
        $this->timeout = (int)$seconds;
    }
    
    private function validateTimeout($value)
    {
        $ok = filter_var($value, FILTER_VALIDATE_INT, array('options' => array(
            'min_range' => 0,
        )));
        if ($ok === false) {
            throw new \InvalidArgumentException("Timeout must be 0 or a positive integer (got $value)");
        }
    }
    
    public function getConnectionString()
    {
        return $this->connectionString;
    }
    
    public function isPersistent()
    {
        return $this->persistent;
    }
    
    public function getConnectionTimeout() {
        return $this->connectionTimeout;
    }
    
    public function getTimeout() {
        return $this->timeout;
    }
    
    /**
     * Allow injecting a resource opened somewhere else. Used in tests.
     *
     * @throws \InvalidArgumentException
     * @param resource $resource 
     */
    public function setResource($resource)
    {
        if (is_resource($resource)) {
            $this->resource = $resource;
        } else {
            throw new \InvalidArgumentException("Expected a resource");
        }
    }

    private function connectIfNotConnected()
    {
        if ($this->isConnected()) {
            return;
        }
        $this->connect();
    }
    
    /**
     * Check to see if the socket is currently available.
     * 
     * UDP might appear to be connected but might fail when writing.  See http://php.net/fsockopen for details.
     * 
     * @return boolean
     */
    public function isConnected()
    {
        return is_resource($this->resource)
               && !feof($this->resource);  // on TCP - other party can close connection.
    }
    
    private function connect()
    {
        $this->createSocketResource();
        $this->setSocketTimeout();
    }
    
    protected function createSocketResource()
    {
        if ($this->persistent) {
            @$resource = pfsockopen($this->connectionString, -1, $errno, $errstr, $this->connectionTimeout);
        } else {
            @$resource = fsockopen($this->connectionString, -1, $errno, $errstr, $this->connectionTimeout);
        }
        if (!$resource) {
            throw new ConnectionException("Failed connecting to $this->connectionString ($errno: $errstr)");
        }
        $this->resource = $resource;
    }
    
    private function setSocketTimeout()
    {
        if (!stream_set_timeout($this->resource, $this->timeout)) {
            throw new ConnectionException("Failed setting timeout with stream_set_timeout()");
        }
    }
    
    protected function writeToSocket($data)
    {
        $length = strlen($data);
        $sent = 0;
        while ($this->isConnected() && $sent < $length) {
            $chunk = $this->fwrite(substr($data, $sent));
            if ($chunk === false) {
                throw new WriteToSocketException("Could not write to socket");
            }
            $sent += $chunk;
            $socketInfo = $this->stream_get_meta_data();
            if ($socketInfo['timed_out']) {
                throw new WriteToSocketException("Write timed-out");
            }
        }
        if (!$this->isConnected() && $sent < $length) {
            throw new WriteToSocketException("End-of-file reached, probably we got disconnected (sent $sent of $length)");
        }
    }
    
    /**
     * Allow mock
     */
    protected function fwrite($data)
    {
        return @fwrite($this->resource, $data);
    }
    
    /**
     * Allow mock
     */
    protected function stream_get_meta_data()
    {
        return stream_get_meta_data($this->resource);
    }
}
