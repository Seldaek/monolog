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

use DateTimeZone;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Utils;
use Monolog\LogRecord;

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
    public const FILE_PER_HOUR = 'Y-m-d-H';
    public const FILE_PER_DAY = 'Y-m-d';
    public const FILE_PER_MONTH = 'Y-m';
    public const FILE_PER_YEAR = 'Y';

    protected string $filename;
    protected int $maxFiles;
    protected bool|null $mustRotate = null;
    protected \DateTimeImmutable $nextRotation;
    protected string $filenameFormat;
    protected string $dateFormat;
    protected DateTimeZone|null $timezone = null;

    /**
     * @param int      $maxFiles       The maximal amount of files to keep (0 means unlimited)
     * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool     $useLocking     Try to lock log file before doing any writes
     */
    public function __construct(string $filename, int $maxFiles = 0, int|string|Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false, string $dateFormat = self::FILE_PER_DAY, string $filenameFormat  = '{filename}-{date}', DateTimeZone|null $timezone = null)
    {
        $this->filename = Utils::canonicalizePath($filename);
        $this->maxFiles = $maxFiles;
        $this->setFilenameFormat($filenameFormat, $dateFormat);
        $this->nextRotation = $this->getNextRotation();
        $this->timezone = $timezone;

        parent::__construct($this->getTimedFilename(), $level, $bubble, $filePermission, $useLocking);
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        parent::close();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        parent::reset();
    }

    /**
     * @return $this
     */
    public function setFilenameFormat(string $filenameFormat, string $dateFormat): self
    {
        $this->setDateFormat($dateFormat);
        if (substr_count($filenameFormat, '{date}') === 0) {
            throw new InvalidArgumentException(
                'Invalid filename format - format must contain at least `{date}`, because otherwise rotating is impossible.'
            );
        }
        $this->filenameFormat = $filenameFormat;
        $this->url = $this->getTimedFilename();
        $this->close();

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        // on the first record written, if the log is new, we rotate (once per day) after the log has been written so that the new file exists
        if (null === $this->mustRotate) {
            $this->mustRotate = null === $this->url || !file_exists($this->url);
        }

        // if the next rotation is expired, then we rotate immediately
        if ($this->nextRotation <= $record->datetime) {
            $this->mustRotate = true;
            $this->close(); // triggers rotation
        }

        parent::write($record);

        if (true === $this->mustRotate) {
            $this->close(); // triggers rotation
        }
    }

    /**
     * Rotates the files.
     */
    protected function rotate(): void
    {
        // update filename
        $this->url = $this->getTimedFilename();
        $this->nextRotation = $this->getNextRotation();

        $this->mustRotate = false;

        // skip GC of old logs if files are unlimited
        if (0 === $this->maxFiles) {
            return;
        }

        $logFiles = $this->findRotatedFiles();

        if ($this->maxFiles >= \count($logFiles)) {
            // no files to remove
            return;
        }

        // Sorting the files by name to remove the older ones
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });

        $basePath = dirname($this->filename);

        foreach (\array_slice($logFiles, $this->maxFiles) as $file) {
            if (is_writable($file)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                    return true;
                });
                unlink($file);

                $dir = dirname($file);
                while ($dir !== $basePath) {
                    $entries = scandir($dir);
                    if ($entries === false || \count(array_diff($entries, ['.', '..'])) > 0) {
                        break;
                    }

                    rmdir($dir);
                    $dir = dirname($dir);
                }
                restore_error_handler();
            }
        }
    }

    protected function getTimedFilename(): string
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = str_replace(
            ['{filename}', '{date}'],
            [$fileInfo['filename'], (new \DateTimeImmutable(timezone: $this->timezone))->format($this->dateFormat)],
            self::appendDirectorySeparator($fileInfo['dirname'] ?? '') . $this->filenameFormat
        );

        if (isset($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        return $timedFilename;
    }

    /**
     * Appends a trailing "/" to a pathinfo() dirname, restoring a stream
     * wrapper URL's second slash when needed.
     *
     * pathinfo() collapses "scheme://subdir" down to "scheme://subdir" (both
     * slashes kept) but "scheme://file" with no subdirectory down to a bare
     * "scheme:" (both slashes dropped) — there's no path segment left for the
     * second slash to attach to. Appending a single "/" is correct for the
     * former (produces "scheme://subdir/") but wrong for the latter (produces
     * "scheme:/", a single-slash form PHP's stream wrapper dispatch does not
     * recognise, so fopen() silently falls through to treating it as a local
     * relative path instead of routing to the wrapper). Detecting the bare
     * "scheme:" form and appending "//" instead restores "scheme://".
     */
    private static function appendDirectorySeparator(string $dirName): string
    {
        if (1 === preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]+:$#', $dirName)) {
            return $dirName.'//';
        }

        return $dirName.'/';
    }

    /**
     * Finds already rotated log files matching the current filename/date format.
     *
     * This intentionally avoids glob(), which does not support stream wrappers
     * (e.g. Drupal's private:// scheme), by walking the base directory with
     * RecursiveDirectoryIterator and matching entries against a regex built
     * from the same tokens getGlobPattern() would have used.
     *
     * @return string[]
     */
    protected function findRotatedFiles(): array
    {
        $fileInfo = pathinfo($this->filename);
        $dirName = $fileInfo['dirname'] ?? '.';
        $baseDir = self::appendDirectorySeparator($dirName);

        if (!is_dir($baseDir)) {
            return [];
        }

        $datePattern = str_replace(
            ['Y', 'y', 'm', 'd', 'H'],
            ['[0-9]{4}', '[0-9]{2}', '[0-9]{2}', '[0-9]{2}', '[0-9]{2}'],
            preg_quote($this->dateFormat, '#')
        );

        $parts = preg_split('{(\{date\}|\{filename\})}', $this->filenameFormat, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return [];
        }

        $pattern = '';
        foreach ($parts as $part) {
            if ('{date}' === $part) {
                $pattern .= $datePattern;
            } elseif ('{filename}' === $part) {
                $pattern .= preg_quote($fileInfo['filename'], '#');
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        if (isset($fileInfo['extension'])) {
            $pattern .= '\.'.preg_quote($fileInfo['extension'], '#');
        }

        $regex = '#^'.preg_quote($baseDir, '#').$pattern.'$#';

        $logFiles = [];

        $directory = new \RecursiveDirectoryIterator(
            $baseDir,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO
        );
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $path => $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', (string) $path);
            if (1 === preg_match($regex, $path)) {
                $logFiles[] = $path;
            }
        }

        return $logFiles;
    }

    protected function getGlobPattern(): string
    {
        $fileInfo = pathinfo($this->filename);
        $glob = str_replace(
            ['{filename}', '{date}'],
            [$fileInfo['filename'], str_replace(
                ['Y', 'y', 'm', 'd', 'H'],
                ['[0-9][0-9][0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]'],
                $this->dateFormat
            )],
            ($fileInfo['dirname'] ?? '') . '/' . $this->filenameFormat
        );
        if (isset($fileInfo['extension'])) {
            $glob .= '.'.$fileInfo['extension'];
        }

        return $glob;
    }

    protected function setDateFormat(string $dateFormat): void
    {
        if (0 === preg_match('{^[Yy](([/_.-]?m)([/_.-]?d([/_.-]?H)?)?)?$}', $dateFormat)) {
            throw new InvalidArgumentException(
                'Invalid date format - format must be one of RotatingFileHandler::FILE_PER_HOUR ("Y-m-d-H"), '.
                'RotatingFileHandler::FILE_PER_DAY ("Y-m-d"), RotatingFileHandler::FILE_PER_MONTH ("Y-m") '.
                'or RotatingFileHandler::FILE_PER_YEAR ("Y"), or you can set one of the '.
                'date formats using slashes, underscores and/or dots instead of dashes.'
            );
        }

        $this->dateFormat = $dateFormat;
    }

    protected function getNextRotation(): \DateTimeImmutable
    {
        return match (str_replace(['/','_','.'], '-', $this->dateFormat)) {
            self::FILE_PER_MONTH => (new \DateTimeImmutable('first day of next month', $this->timezone))->setTime(0, 0, 0),
            self::FILE_PER_YEAR => (new \DateTimeImmutable('first day of January next year', $this->timezone))->setTime(0, 0, 0),
            default => (new \DateTimeImmutable('tomorrow', $this->timezone))->setTime(0, 0, 0),
        };
    }
}
