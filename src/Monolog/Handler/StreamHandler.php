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
    protected const DEFAULT_LOCK_INITIAL_SLEEP_MS = 150; // initialSleepTime * maxRetries => 150 + 300 + 600 + 1200 + 2400 = 4650ms Max Waiting Time
    protected int $streamChunkSize;
    /** @var resource|null */
    protected $stream = null;
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
            $this->createDir($this->getDirFromStream($url));
            $this->errorMessage = null;
            set_error_handler($this->customErrorHandler(...));

            $stream = null; // initialize outside of try block to be available in scope further down
            try {
                $stream = $this->attemptOperationWithExponentialRandomizedRetries(
                    function() use($url)  {
                        $stream = fopen($url, $this->fileOpenMode);
                        if ($this->filePermission !== null) {
                            @chmod($url, $this->filePermission);
                        }
                        return $stream;
                    });
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
            if ($stream) {
                // Attempt to acquire a file lock
                $lockStream = $this->attemptOperationWithExponentialRandomizedRetries(
                    static fn() => flock($stream, LOCK_EX)
                );
                if(!$lockStream) {
                    throw new \UnexpectedValueException(sprintf('The stream or file for "%s" could not be locked: ', $url));
                }
            }
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
            if($stream) {
                flock($stream, LOCK_UN);
            }
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
     * @param callable(): (resource|bool|mixed) $operation The operation to attempt.
     *                                               - For operations like opening a file, it should return a resource on success or throw an exception on critical failure.
     *                                               - For operations like acquiring a lock, it should return true on success or false on recoverable failure (to trigger a retry).
     * @param int      $maxRetries         Maximum number of retries.
     * @param int      $initialSleepTimeMs Initial sleep time in milliseconds for backoff.
     * @return mixed The result of the successful operation (a resource handle from file opening, or true from lock acquisition).
     * @throws \RuntimeException If the operation failed after all retries (e.g., lock acquisition repeatedly returning false, or if the callable itself throws an exception that is not caught).
     */
    private function attemptOperationWithExponentialRandomizedRetries(callable $operation, int $maxRetries = self::DEFAULT_LOCK_MAX_RETRIES, int $initialSleepTimeMs = self::DEFAULT_LOCK_INITIAL_SLEEP_MS): mixed
    {
        $upperBoundSleepMsOfPreviousRetry = 0; // Initialize for the first retry's lower bound

        for ($retries = 0; $retries <= $maxRetries; $retries++) {
            // First try is without waiting
            try {
                if ($result = $operation()) {
                    return $result; // Operation succeeded
                }
            }
            catch (\Throwable $e) {
                // Handle the exception if needed, but continue to retry
                if ($retries >= $maxRetries) {
                    throw $e; // Rethrow the exception if max retries reached
                }
            }

            if ($retries < $maxRetries) {
                // Exponential backoff: initial, initial*2, initial*4, initial*6, ...
                $currentUpperSleepBoundMs = $initialSleepTimeMs * ($retries === 0 ? 1 : 2 * $retries);

                // The random sleep interval is between the upper bound of the previous retry's sleep
                // and the upper bound of the current retry's sleep.
                $lowerRandBoundMs = $upperBoundSleepMsOfPreviousRetry;
                $upperRandBoundMs = $currentUpperSleepBoundMs;

                // mt_rand requires lower_bound <= upper_bound. This holds if $initialSleepTimeMs >= 0.
                $actualSleepTimeMs = mt_rand($lowerRandBoundMs, $upperRandBoundMs);

                if ($actualSleepTimeMs > 0) {
                    usleep($actualSleepTimeMs * 1000); // usleep takes microseconds
                }

                // For the next iteration, the current upper bound becomes the "previous upper bound".
                $upperBoundSleepMsOfPreviousRetry = $currentUpperSleepBoundMs;
            }
        }

        // Throw exception Operation failed after all retries
        throw new \RuntimeException(
            sprintf('The operation failed after %d attempts.', $maxRetries + 1)
        );
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

    /**
     * @param bool            $urlsIsAlreadyDir     url is_dir() == true, needed for recursive calls
     *
     * @throws \UnexpectedValueException If url can't be created
     */
    private function createDir(string $dir): void
    {
        // Do not try to create dir if it has already been tried.
        if (true === $this->dirCreated) {
            return;
        }

        if (null !== $dir && !is_dir($dir)) {
            $dirCreatedStatus = false;

            if ($this->useLocking) {
                // Lock parent directory before creating new directory
                $lockDir = dirname($dir);

                // Possible limitations on windows, quil old report, but coulnd't find anything new and can't test on Windows
                // Can't aqcuire lock on directory itself on windows, so using helper file
                // https://stackoverflow.com/questions/17678294/how-to-lock-a-directory-for-exclusive-access-in-php-on-windows
                if (PHP_OS_FAMILY === 'Windows') {
                    // If the directory itself doesn't exists already,
                    // The lock of the file inside will also fail.
                    // The helper so needs to be next to directory
                    // On Windows, the lock file is placed besides $dir
                    $helperResourcePath = $lockDir . DIRECTORY_SEPARATOR . '.monolog_mkdir_lock';
                } else {
                    // On non-Windows systems, use the lockDir directly
                    $helperResourcePath = $lockDir;
                }

                // Initialize to be available in finally
                $resourceStream = null;
                $lockOnResourceStream = null;
                // If directory already exists, we can skip the lock acquisition
                if (is_dir($helperResourcePath)) {

                    // Attempt to acquire a file steam
                    $resourceStream = $this->attemptOperationWithExponentialRandomizedRetries(
                        function() use($helperResourcePath)  {
                            $lockMode = is_dir($helperResourcePath) ? 'w+' : 'c+';
                            $ressourceStream = @fopen($helperResourcePath, $lockMode);
                            if (!$ressourceStream) {
                                if (is_dir($helperResourcePath)) { // If it's a directory and already exists, we can't get exclusive access
                                    return @fopen($helperResourcePath, 'r');
                                }
                                throw new \UnexpectedValueException(
                                    sprintf('Unable to create lock file for directory creation at "%s"', $helperResourcePath)
                                );
                            }
                            return $ressourceStream;
                        });
                    // Attempt to acquire a file lock
                    $lockOnResourceStream = $this->attemptOperationWithExponentialRandomizedRetries(
                        fn() => flock($resourceStream, LOCK_EX)
                    );

                    if (!$lockOnResourceStream) {
                        fclose($resourceStream);
                        // Attempt to remove the lock file if lock acquisition failed, as it might be stale.
                        // Use @ to suppress errors if unlink fails (e.g. permissions, file not found).
                        if(!is_dir($helperResourcePath)) {
                            @unlink($helperResourcePath);
                        }
                        throw new \UnexpectedValueException(
                            sprintf('Unable to acquire lock for directory creation at "%s" after %d attempts.', $helperResourcePath, self::DEFAULT_LOCK_MAX_RETRIES + 1)
                        );
                    }
                }
                // Parent directory locked and loaded, now we can create the new directory
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
                } finally { // Try to release the lock and close the stream
                    if($resourceStream != null) {
                        flock($resourceStream, LOCK_UN);
                        fclose($resourceStream);
                        // If we created a lock helper FILE, we should remove it
                        // (helper DIRECTORY needs to stay, contains streams)
                        if(!is_dir($helperResourcePath)) {
                            // This is a best effort cleanup.
                            // Use @ to suppress errors if unlink fails (e.g. permissions, file not found).
                            @unlink($helperResourcePath);
                        }
                    }
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
        // only set to true if finanlly created
        if(is_dir($dir)){
            $this->dirCreated = true;
        }
    }
}
