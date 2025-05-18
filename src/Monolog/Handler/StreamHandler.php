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

use Monolog\Level;
use Monolog\Utils;
use Monolog\LogRecord;

/**
 * Stores to any stream resource
 *
 * Can be used to store into php://stderr, remote and local files, etc.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class StreamHandler extends AbstractProcessingHandler
{
    protected const MAX_CHUNK_SIZE = 2147483647;
    /** 10MB */
    protected const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;
    protected const DEFAULT_LOCK_MAX_RETRIES = 5;
    protected const DEFAULT_LOCK_INITIAL_SLEEP_MS = 15;
    protected int $streamChunkSize;
    /** @var resource|null */
    protected $stream;
    protected string|null $url = null;
    private string|null $errorMessage = null;
    protected int|null $filePermission;
    protected bool $useLocking;
    protected string $fileOpenMode;
    /** @var true|null */
    private bool|null $dirCreated = null;
    private bool $retrying = false;

    /**
     * @param resource|string $stream         If a missing path can't be created, an UnexpectedValueException will be thrown on first write
     * @param int|null        $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool            $useLocking     Try to lock log file before doing any writes
     * @param string          $fileOpenMode   The fopen() mode used when opening a file, if $stream is a file path
     *
     * @throws \InvalidArgumentException If stream is not a resource or string
     */
    public function __construct($stream, int|string|Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false, string $fileOpenMode = 'a')
    {
        parent::__construct($level, $bubble);

        if (($phpMemoryLimit = Utils::expandIniShorthandBytes(\ini_get('memory_limit'))) !== false) {
            if ($phpMemoryLimit > 0) {
                // use max 10% of allowed memory for the chunk size, and at least 100KB
                $this->streamChunkSize = min(static::MAX_CHUNK_SIZE, max((int) ($phpMemoryLimit / 10), 100 * 1024));
            } else {
                // memory is unlimited, set to the default 10MB
                $this->streamChunkSize = static::DEFAULT_CHUNK_SIZE;
            }
        } else {
            // no memory limit information, set to the default 10MB
            $this->streamChunkSize = static::DEFAULT_CHUNK_SIZE;
        }

        if (\is_resource($stream)) {
            $this->stream = $stream;

            stream_set_chunk_size($this->stream, $this->streamChunkSize);
        } elseif (\is_string($stream)) {
            $this->url = Utils::canonicalizePath($stream);
        } else {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }

        $this->fileOpenMode = $fileOpenMode;
        $this->filePermission = $filePermission;
        $this->useLocking = $useLocking;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        parent::reset();

        // auto-close on reset to make sure we periodically close the file in long running processes
        // as long as they correctly call reset() between jobs
        if ($this->url !== null && $this->url !== 'php://memory') {
            $this->close();
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (null !== $this->url && \is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
        $this->dirCreated = null;
    }

    /**
     * Return the currently active stream if it is open
     *
     * @return resource|null
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Return the stream URL if it was configured with a URL and not an active resource
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getStreamChunkSize(): int
    {
        return $this->streamChunkSize;
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        if (!\is_resource($this->stream)) {
            $url = $this->url;
            if (null === $url || '' === $url) {
                throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().' . Utils::getRecordMessageForException($record));
            }
            $this->createDir($url);
            $this->errorMessage = null;
            set_error_handler($this->customErrorHandler(...));

            try {
                $stream = fopen($url, $this->fileOpenMode);
                if ($this->filePermission !== null) {
                    @chmod($url, $this->filePermission);
                }
            } finally {
                restore_error_handler();
            }
            if (!\is_resource($stream)) {
                $this->stream = null;

                throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened in append mode: '.$this->errorMessage, $url) . Utils::getRecordMessageForException($record));
            }
            stream_set_chunk_size($stream, $this->streamChunkSize);
            $this->stream = $stream;
        }

        $stream = $this->stream;
        if ($this->useLocking) {
            // ignoring errors here, there's not much we can do about them
            flock($stream, LOCK_EX);
        }

        $this->errorMessage = null;
        set_error_handler($this->customErrorHandler(...));
        try {
            $this->streamWrite($stream, $record);
        } finally {
            restore_error_handler();
        }
        if ($this->errorMessage !== null) {
            $error = $this->errorMessage;
            // close the resource if possible to reopen it, and retry the failed write
            if (!$this->retrying && $this->url !== null && $this->url !== 'php://memory') {
                $this->retrying = true;
                $this->close();
                $this->write($record);

                return;
            }

            throw new \UnexpectedValueException('Writing to the log file failed: '.$error . Utils::getRecordMessageForException($record));
        }

        $this->retrying = false;
        if ($this->useLocking) {
            flock($stream, LOCK_UN);
        }
    }

    /**
     * Write to stream
     * @param resource $stream
     */
    protected function streamWrite($stream, LogRecord $record): void
    {
        fwrite($stream, (string) $record->formatted);
    }

    /**
     * Attempts to execute an operation that might fail (e.g., acquiring a lock), with a retry mechanism.
     *
     * @param callable $operation          The operation to attempt. Should return true on success, false on failure.
     * @param int      $maxRetries         Maximum number of retries.
     * @param int      $initialSleepTimeMs Initial sleep time in milliseconds for backoff.
     * @return bool True if the operation succeeded, false otherwise.
     */
    private function attemptOperationWithRetry(callable $operation, int $maxRetries = self::DEFAULT_LOCK_MAX_RETRIES, int $initialSleepTimeMs = self::DEFAULT_LOCK_INITIAL_SLEEP_MS): bool
    {
        for ($retries = 0; $retries <= $maxRetries; $retries++) {
            if ($operation()) {
                return true; // Operation succeeded
            }

            if ($retries < $maxRetries) {
                // Exponential backoff: initial, initial*2, initial*4, initial*6, ...
                $sleepTimeMs = $initialSleepTimeMs * ($retries === 0 ? 1 : 2 * $retries);
                usleep($sleepTimeMs * 1000); // usleep takes microseconds
            }
        }

        return false; // Operation failed after all retries
    }

    private function customErrorHandler(int $code, string $msg): bool
    {
        $this->errorMessage = preg_replace('{^(fopen|mkdir|fwrite)\(.*?\): }', '', $msg);

        return true;
    }

    private function getDirFromStream(string $stream): ?string
    {
        $pos = strpos($stream, '://');
        if ($pos === false) {
            return \dirname($stream);
        }

        if ('file://' === substr($stream, 0, 7)) {
            return \dirname(substr($stream, 7));
        }

        return null;
    }

    private function createDir(string $url): void
    {
        // Do not try to create dir if it has already been tried.
        if (true === $this->dirCreated) {
            return;
        }

        $dir = $this->getDirFromStream($url);
        if (null !== $dir && !is_dir($dir)) {
            $dirCreatedStatus = false;

            if ($this->useLocking) {
                // Create a lock file in the parent directory to prevent race conditions
                $lockDir = dirname($dir);
                $lockFile = $lockDir . DIRECTORY_SEPARATOR . '.monolog_mkdir_lock';

                // Make sure the parent directory exists
                if (!is_dir($lockDir)) {
                    // Recursively create parent directories first
                    $this->createDir($lockDir);
                }

                // Attempt to acquire a lock
                $lockFileStream = @fopen($lockFile, 'c+');
                if (!$lockFileStream) {
                    throw new \UnexpectedValueException(
                        sprintf('Unable to create lock file for directory creation at "%s"', $lockFile)
                    );
                }

                $lockAcquired = $this->attemptOperationWithRetry(
                    fn(): bool => flock($lockFileStream, LOCK_EX)
                );

                if (!$lockAcquired) {
                    fclose($lockFileStream);
                    // Attempt to remove the lock file if lock acquisition failed, as it might be stale.
                    // Use @ to suppress errors if unlink fails (e.g. permissions, file not found).
                    @unlink($lockFile);
                    throw new \UnexpectedValueException(
                        sprintf('Unable to acquire lock for directory creation at "%s" after %d attempts.', $lockFile, self::DEFAULT_LOCK_MAX_RETRIES + 1)
                    );
                }

                try {
                    // Check again if directory exists (might have been created by another process while waiting for lock)
                    if (!is_dir($dir)) {
                        // Only use custom error handler around mkdir
                        $this->errorMessage = null;
                        set_error_handler(function (...$args) {
                            return $this->customErrorHandler(...$args);
                        });
                        try {
                            $dirCreatedStatus = mkdir($dir, 0777, true);
                        } finally {
                            restore_error_handler();
                        }

                        if (false === $dirCreatedStatus && !is_dir($dir)) {
                            if ($this->errorMessage === null) {
                                $this->errorMessage = 'Directory creation failed';
                            }

                            if (strpos((string) $this->errorMessage, 'File exists') === false) {
                                throw new \UnexpectedValueException(
                                    sprintf('There is no existing directory at "%s" and it could not be created: %s', $dir, $this->errorMessage)
                                );
                            }
                        }
                    } else {
                        $dirCreatedStatus = true;
                    }
                } finally {
                    // Always release the lock
                    flock($lockFileStream, LOCK_UN);
                    fclose($lockFileStream);
                    @unlink($lockFile); // Best effort to clean up the lock file
                }
            } else {
                // No locking requested, create directory directly
                $this->errorMessage = null;
                set_error_handler(function (...$args) {
                    return $this->customErrorHandler(...$args);
                });
                try {
                    $dirCreatedStatus = mkdir($dir, 0777, true);
                } finally {
                    restore_error_handler();
                }

                if (false === $dirCreatedStatus && !is_dir($dir)) {
                    if ($this->errorMessage === null) {
                        $this->errorMessage = 'Directory creation failed';
                    }

                    if (strpos((string) $this->errorMessage, 'File exists') === false) {
                        throw new \UnexpectedValueException(
                            sprintf('There is no existing directory at "%s" and it could not be created: %s', $dir, $this->errorMessage)
                        );
                    }
                }
            }
        }
        $this->dirCreated = true;
    }
}
