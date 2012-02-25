<?php
/**
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */

namespace Monolog\Handler\SocketHandler;

use Monolog\Handler\SocketHandler\Exception\ConnectionException;
use Monolog\Handler\SocketHandler\Exception\WriteToSocketException;

/**
 * Small class which writes to a socket.
 * Timeout settings must be set before first write to have any effect.
 * 
 * @see http://php.net/manual/en/function.fsockopen.php
 */
class Socket
{
    protected $connectionString;
    protected $connectionTimeout;
    protected $resource;
    private $timeout = 0;
    
    /**
     * @param string $connectionString As interpreted by fsockopen()
     */
    public function __construct($connectionString)
    {
        $this->connectionString = $connectionString;
        $this->connectionTimeout = (float)ini_get('default_socket_timeout');
    }
    
    public function getConnectionString()
    {
        return $this->connectionString;
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
    
    public function getConnectionTimeout() {
        return $this->connectionTimeout;
    }
    
    public function getTimeout() {
        return $this->timeout;
    }
    
    public function close()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
            $this->resource = null;
        }
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

    /**
     * Connect (if necessary) and write to the socket
     * 
     * @throws Monolog\Handler\SocketHandler\Exception\ConnectionException
     * @throws Monolog\Handler\SocketHandler\Exception\WriteToSocketException
     * @param string $string
     */
    public function write($string)
    {
        $this->connectIfNotConnected();
        $this->writeToSocket($string);
    }
    
    protected function connectIfNotConnected()
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
    
    protected function connect()
    {
        $this->createSocketResource();
        $this->setSocketTimeout();
    }
    
    protected function createSocketResource()
    {
        @$resource = fsockopen($this->connectionString, -1, $errno, $errstr, $this->connectionTimeout);
        if (!$resource) {
            throw new ConnectionException("Failed connecting to $this->connectionString ($errno: $errstr)");
        }
        $this->resource = $resource;
    }
    
    protected function setSocketTimeout()
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
            @$chunk = fwrite($this->resource, substr($data, $sent));
            if ($chunk === false) {
                throw new WriteToSocketException("Could not write to socket");
            }
            $sent += $chunk;
            $socketInfo = stream_get_meta_data($this->resource);
            if ($socketInfo['timed_out']) {
                throw new WriteToSocketException("Write timed-out");
            }
        }
        if (!$this->isConnected() && $sent < $length) {
            throw new WriteToSocketException("End-of-file reached, probably we got disconnected (sent $sent of $length)");
        }
    }
}
