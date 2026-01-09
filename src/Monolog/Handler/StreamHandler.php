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
 */
class StreamHandler extends AbstractProcessingHandler
{
    protected const MAX_CHUNK_SIZE = 2147483647;
    protected const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;

    protected int $streamChunkSize;
    /** @var resource|null */
    protected $stream;
    protected ?string $url = null;
    private ?string $errorMessage = null;
    protected ?int $filePermission;
    protected bool $useLocking;
    protected string $fileOpenMode;
    private ?bool $dirCreated = null;
    private bool $retrying = false;
    private ?int $inodeUrl = null;

    public function __construct(
        $stream,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
        string $fileOpenMode = 'a'
    ) {
        parent::__construct($level, $bubble);

        if (($phpMemoryLimit = Utils::expandIniShorthandBytes(\ini_get('memory_limit'))) !== false) {
            if ($phpMemoryLimit > 0) {
                $this->streamChunkSize = min(
                    self::MAX_CHUNK_SIZE,
                    max((int) ($phpMemoryLimit / 10), 100 * 1024)
                );
            } else {
                $this->streamChunkSize = self::DEFAULT_CHUNK_SIZE;
            }
        } else {
            $this->streamChunkSize = self::DEFAULT_CHUNK_SIZE;
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

    protected function write(LogRecord $record): void
    {
        if (!\is_resource($this->stream)) {
            $url = $this->url;

            if ($url === null || $url === '') {
                throw new \LogicException(
                    'Missing stream url, the stream can not be opened.'
                    . Utils::getRecordMessageForException($record)
                );
            }

            $this->createDir($url);
            $this->errorMessage = null;

            set_error_handler($this->customErrorHandler(...));
            try {
                $this->stream = fopen($url, $this->fileOpenMode);
                if ($this->filePermission !== null) {
                    @chmod($url, $this->filePermission);
                }
            } finally {
                restore_error_handler();
            }

            if (!\is_resource($this->stream)) {
                throw new \UnexpectedValueException(
                    sprintf(
                        'The stream or file "%s" could not be opened in append mode: %s',
                        $url,
                        $this->errorMessage
                    )
                    . Utils::getRecordMessageForException($record)
                );
            }

            stream_set_chunk_size($this->stream, $this->streamChunkSize);
        }

        $stream = $this->stream;

        if ($this->useLocking) {
            flock($stream, LOCK_EX);
        }

        $this->errorMessage = null;
        set_error_handler($this->customErrorHandler(...));

        try {
            // 🔥 FIX: loop on fwrite for non-blocking streams
            $formatted = (string) $record->formatted;

            $length = strlen($formatted);
            $written = 0;

            while ($written < $length) {
                $result = @fwrite($stream, substr($formatted, $written));

                if ($result === false) {
                    break;
                }

                $written += $result;
            }
        } finally {
            restore_error_handler();
        }

        if ($this->errorMessage !== null) {
            throw new \UnexpectedValueException(
                'Writing to the log file failed: '
                . $this->errorMessage
                . Utils::getRecordMessageForException($record)
            );
        }

        if ($this->useLocking) {
            flock($stream, LOCK_UN);
        }
    }

    /**
     * Write to stream (kept for BC / overrides)
     *
     * @param resource $stream
     */
    protected function streamWrite($stream, LogRecord $record): void
    {
        fwrite($stream, (string) $record->formatted);
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
        if ($this->dirCreated === true) {
            return;
        }

        $dir = $this->getDirFromStream($url);
        if ($dir !== null && !is_dir($dir)) {
            $this->errorMessage = null;
            set_error_handler($this->customErrorHandler(...));
            $status = mkdir($dir, 0777, true);
            restore_error_handler();

            if ($status === false && !is_dir($dir)) {
                throw new \UnexpectedValueException(
                    sprintf('There is no existing directory at "%s" and it could not be created: %s', $dir, $this->errorMessage)
                );
            }
        }

        $this->dirCreated = true;
    }
}
