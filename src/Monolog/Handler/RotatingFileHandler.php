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

use InvalidArgumentException;
use Monolog\Logger;
use Monolog\Utils;

/**
 * Stores logs to files that are rotated every day and a limited number of files are kept.
 *
 * This rotation is only intended to be used as a workaround. Using logrotate to
 * handle the rotation is strongly encouraged when you can use it.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class RotatingFileHandler extends StreamHandler
{
    public const FILE_PER_DAY = 'Y-m-d';
    public const FILE_PER_MONTH = 'Y-m';
    public const FILE_PER_YEAR = 'Y';

    /** @var string */
    protected $filename;
    /** @var int */
    protected $maxFiles;
    /** @var bool */
    protected $mustRotate;
    /** @var \DateTimeImmutable */
    protected $nextRotation;
    /** @var string */
    protected $filenameFormat;
    /** @var string */
    protected $dateFormat;

    /**
     * @param string     $filename
     * @param int        $maxFiles       The maximal amount of files to keep (0 means unlimited)
     * @param int|null   $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool       $useLocking     Try to lock log file before doing any writes
     */
    public function __construct(string $filename, int $maxFiles = 0, $level = Logger::DEBUG, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false)
    {
        $this->filename = Utils::canonicalizePath($filename);
        $this->maxFiles = $maxFiles;
        $this->nextRotation = new \DateTimeImmutable('tomorrow');
        $this->filenameFormat = '{filename}-{date}';
        $this->dateFormat = static::FILE_PER_DAY;

        parent::__construct($this->getTimedFilename(), $level, $bubble, $filePermission, $useLocking);
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        parent::close();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reset()
    {
        parent::reset();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    public function setFilenameFormat(string $filenameFormat, string $dateFormat): self
    {
        if (!preg_match('{^[Yy](([/_.-]?m)([/_.-]?d)?)?$}', $dateFormat)) {
            throw new InvalidArgumentException(
                'Invalid date format - format must be one of '.
                'RotatingFileHandler::FILE_PER_DAY ("Y-m-d"), RotatingFileHandler::FILE_PER_MONTH ("Y-m") '.
                'or RotatingFileHandler::FILE_PER_YEAR ("Y"), or you can set one of the '.
                'date formats using slashes, underscores and/or dots instead of dashes.'
            );
        }
        if (substr_count($filenameFormat, '{date}') === 0) {
            throw new InvalidArgumentException(
                'Invalid filename format - format must contain at least `{date}`, because otherwise rotating is impossible.'
            );
        }
        $this->filenameFormat = $filenameFormat;
        $this->dateFormat = $dateFormat;
        $this->url = $this->getTimedFilename();
        $this->close();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record): void
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if (null === $this->mustRotate) {
            $this->mustRotate = null === $this->url || !file_exists($this->url);
        }

        if ($this->nextRotation <= $record['datetime']) {
            $this->mustRotate = true;
            $this->close();
        }

        parent::write($record);
    }

    /**
     * Rotates the files.
     */
    protected function rotate(): void
    {
        // update filename
        $this->url = $this->getTimedFilename();
        $this->nextRotation = new \DateTimeImmutable('tomorrow');

        // skip GC of old logs if files are unlimited
        if (0 === $this->maxFiles) {
            return;
        }

        $logFiles = glob($this->getGlobPattern());
        if (false === $logFiles) {
            // failed to glob
            return;
        }

        if ($this->maxFiles >= count($logFiles)) {
            // no files to remove
            return;
        }

        // Sorting the files by name to remove the older ones
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });

        foreach (array_slice($logFiles, $this->maxFiles) as $file) {
            if (is_writable($file)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                    return false;
                });
                unlink($file);
                restore_error_handler();
            }
        }

        $this->mustRotate = false;
    }

    protected function getTimedFilename(): string
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = str_replace(
            ['{filename}', '{date}'],
            [$fileInfo['filename'], date($this->dateFormat)],
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );

        if (isset($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        return $timedFilename;
    }

    protected function getGlobPattern(): string
    {
        $fileInfo = pathinfo($this->filename);
        $glob = str_replace(
            ['{filename}', '{date}'],
            [$fileInfo['filename'], str_replace(
                ['Y', 'y', 'm', 'd'],
                ['[0-9][0-9][0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]'],
                $this->dateFormat)
            ],
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );
        if (isset($fileInfo['extension'])) {
            $glob .= '.'.$fileInfo['extension'];
        }

        return $glob;
    }
}
