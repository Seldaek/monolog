<?php declare(strict_types=1);

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

/**
 * Stores to any socket - uses fsockopen() or pfsockopen().
 *
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 * @see    http://php.net/manual/en/function.fsockopen.php
 */
class SocketHandler extends AbstractProcessingHandler
{
    private $connectionString;
    private $connectionTimeout;
    /** @var resource|null */
    private $resource;
    /** @var float */
    private $timeout = 0.0;
    /** @var float */
    private $writingTimeout = 10.0;
    private $lastSentBytes = null;
    /** @var int */
    private $chunkSize = null;
    private $persistent = false;
    private $errno;
    private $errstr;
    /** @var ?float */
    private $lastWritingAt;

    /**
     * @param string     $connectionString Socket connection string
     * @param int|string $level            The minimum logging level at which this handler will be triggered
     * @param bool       $bubble           Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(string $connectionString, $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->connectionString = $connectionString;
        $this->connectionTimeout = (float) ini_get('default_socket_timeout');
    }

    /**
     * Connect (if necessary) and write to the socket
     *
     * @param array $record
     *
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     */
    protected function write(array $record): void
    {
        $this->connectIfNotConnected();
        $data = $this->generateDataStream($record);
        $this->writeToSocket($data);
    }

    /**
     * We will not close a PersistentSocket instance so it can be reused in other requests.
     */
    public function close(): void
    {
        if (!$this->isPersistent()) {
            $this->closeSocket();
        }
    }

    /**
     * Close socket, if open
     */
    public function closeSocket(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
            $this->resource = null;
        }
    }

    /**
     * Set socket connection to be persistent. It only has effect before the connection is initiated.
     */
    public function setPersistent(bool $persistent): self
    {
        $this->persistent = $persistent;

        return $this;
    }

    /**
     * Set connection timeout.  Only has effect before we connect.
     *
     * @see http://php.net/manual/en/function.fsockopen.php
     */
    public function setConnectionTimeout(float $seconds): self
    {
        $this->validateTimeout($seconds);
        $this->connectionTimeout = $seconds;

        return $this;
    }

    /**
     * Set write timeout. Only has effect before we connect.
     *
     * @see http://php.net/manual/en/function.stream-set-timeout.php
     */
    public function setTimeout(float $seconds): self
    {
        $this->validateTimeout($seconds);
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set writing timeout. Only has effect during connection in the writing cycle.
     *
     * @param float $seconds 0 for no timeout
     */
    public function setWritingTimeout(float $seconds): self
    {
        $this->validateTimeout($seconds);
        $this->writingTimeout = $seconds;

        return $this;
    }

    /**
     * Set chunk size. Only has effect during connection in the writing cycle.
     */
    public function setChunkSize(int $bytes): self
    {
        $this->chunkSize = $bytes;

        return $this;
    }

    /**
     * Get current connection string
     */
    public function getConnectionString(): string
    {
        return $this->connectionString;
    }

    /**
     * Get persistent setting
     */
    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Get current connection timeout setting
     */
    public function getConnectionTimeout(): float
    {
        return $this->connectionTimeout;
    }

    /**
     * Get current in-transfer timeout
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Get current local writing timeout
     *
     * @return float
     */
    public function getWritingTimeout(): float
    {
        return $this->writingTimeout;
    }

    /**
     * Get current chunk size
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Check to see if the socket is currently available.
     *
     * UDP might appear to be connected but might fail when writing.  See http://php.net/fsockopen for details.
     */
    public function isConnected(): bool
    {
        return is_resource($this->resource)
            && !feof($this->resource);  // on TCP - other party can close connection.
    }

    /**
     * Wrapper to allow mocking
     */
    protected function pfsockopen()
    {
        return @pfsockopen($this->connectionString, -1, $this->errno, $this->errstr, $this->connectionTimeout);
    }

    /**
     * Wrapper to allow mocking
     */
    protected function fsockopen()
    {
        return @fsockopen($this->connectionString, -1, $this->errno, $this->errstr, $this->connectionTimeout);
    }

    /**
     * Wrapper to allow mocking
     *
     * @see http://php.net/manual/en/function.stream-set-timeout.php
     */
    protected function streamSetTimeout()
    {
        $seconds = floor($this->timeout);
        $microseconds = round(($this->timeout - $seconds) * 1e6);

        return stream_set_timeout($this->resource, (int) $seconds, (int) $microseconds);
    }

    /**
     * Wrapper to allow mocking
     *
     * @see http://php.net/manual/en/function.stream-set-chunk-size.php
     */
    protected function streamSetChunkSize()
    {
        return stream_set_chunk_size($this->resource, $this->chunkSize);
    }

    /**
     * Wrapper to allow mocking
     */
    protected function fwrite($data)
    {
        return @fwrite($this->resource, $data);
    }

    /**
     * Wrapper to allow mocking
     */
    protected function streamGetMetadata()
    {
        return stream_get_meta_data($this->resource);
    }

    private function validateTimeout($value)
    {
        $ok = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($ok === false || $value < 0) {
            throw new \InvalidArgumentException("Timeout must be 0 or a positive float (got $value)");
        }
    }

    private function connectIfNotConnected()
    {
        if ($this->isConnected()) {
            return;
        }
        $this->connect();
    }

    protected function generateDataStream(array $record): string
    {
        return (string) $record['formatted'];
    }

    /**
     * @return resource|null
     */
    protected function getResource()
    {
        return $this->resource;
    }

    private function connect(): void
    {
        $this->createSocketResource();
        $this->setSocketTimeout();
        $this->setStreamChunkSize();
    }

    private function createSocketResource(): void
    {
        if ($this->isPersistent()) {
            $resource = $this->pfsockopen();
        } else {
            $resource = $this->fsockopen();
        }
        if (!$resource) {
            throw new \UnexpectedValueException("Failed connecting to $this->connectionString ($this->errno: $this->errstr)");
        }
        $this->resource = $resource;
    }

    private function setSocketTimeout(): void
    {
        if (!$this->streamSetTimeout()) {
            throw new \UnexpectedValueException("Failed setting timeout with stream_set_timeout()");
        }
    }

    private function setStreamChunkSize(): void
    {
        if ($this->chunkSize && !$this->streamSetChunkSize()) {
            throw new \UnexpectedValueException("Failed setting chunk size with stream_set_chunk_size()");
        }
    }

    private function writeToSocket(string $data): void
    {
        $length = strlen($data);
        $sent = 0;
        $this->lastSentBytes = $sent;
        while ($this->isConnected() && $sent < $length) {
            if (0 == $sent) {
                $chunk = $this->fwrite($data);
            } else {
                $chunk = $this->fwrite(substr($data, $sent));
            }
            if ($chunk === false) {
                throw new \RuntimeException("Could not write to socket");
            }
            $sent += $chunk;
            $socketInfo = $this->streamGetMetadata();
            if ($socketInfo['timed_out']) {
                throw new \RuntimeException("Write timed-out");
            }

            if ($this->writingIsTimedOut($sent)) {
                throw new \RuntimeException("Write timed-out, no data sent for `{$this->writingTimeout}` seconds, probably we got disconnected (sent $sent of $length)");
            }
        }
        if (!$this->isConnected() && $sent < $length) {
            throw new \RuntimeException("End-of-file reached, probably we got disconnected (sent $sent of $length)");
        }
    }

    private function writingIsTimedOut(int $sent): bool
    {
        // convert to ms
        if (0.0 == $this->writingTimeout) {
            return false;
        }

        if ($sent !== $this->lastSentBytes) {
            $this->lastWritingAt = microtime(true);
            $this->lastSentBytes = $sent;

            return false;
        } else {
            usleep(100);
        }

        if ((microtime(true) - $this->lastWritingAt) >= $this->writingTimeout) {
            $this->closeSocket();

            return true;
        }

        return false;
    }
}
