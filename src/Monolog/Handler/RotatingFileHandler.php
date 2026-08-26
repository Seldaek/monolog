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

        $basePath = self::normalizeDirectorySeparators(dirname($this->filename));

        foreach (\array_slice($logFiles, $this->maxFiles) as $file) {
            if (is_writable($file)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                    return true;
                });
                unlink($file);

                $dir = self::normalizeDirectorySeparators(dirname($file));
                while ($dir !== $basePath) {
                    $entries = scandir($dir);
                    if ($entries === false || \count(array_diff($entries, ['.', '..'])) > 0) {
                        break;
                    }

                    rmdir($dir);
                    $dir = self::normalizeDirectorySeparators(dirname($dir));
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
     * Appends the trailing "/" to a pathinfo() dirname.
     *
     * pathinfo() reduces a wrapper URL with no subdirectory ("scheme://foo.log")
     * to a bare "scheme:", and PHP does not route the single-slash "scheme:/"
     * to the wrapper at all, so that case needs both slashes back. The pattern
     * deliberately requires two characters before the colon so a Windows drive
     * letter is left alone.
     */
    private static function appendDirectorySeparator(string $dirName): string
    {
        if (1 === preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]+:$#', $dirName)) {
            return $dirName.'//';
        }

        return $dirName.'/';
    }

    /**
     * Rewrites Windows directory separators to "/".
     *
     * SPL joins directory entries with DIRECTORY_SEPARATOR, so on Windows the
     * iterator yields "C:\\logs\\foo.rot" while the pattern is built from a
     * pathinfo() dirname. Both sides have to be normalised, and only on Windows:
     * on POSIX a backslash is a legal filename character.
     */
    private static function normalizeDirectorySeparators(string $path): string
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return $path;
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Finds the log files that previous rotations left behind.
     *
     * glob() cannot see through stream wrappers (Drupal's private:// for one),
     * so the pattern is translated to a regex and matched against a directory
     * walk instead. It still comes from getGlobPattern(), so overriding that
     * keeps changing which files the cleanup considers. Unlike glob(), symlinked
     * directories are not followed, which also rules out symlink loops.
     *
     * @return string[]
     */
    protected function findRotatedFiles(): array
    {
        $pattern = self::normalizeDirectorySeparators($this->getGlobPattern());

        // split off the literal directory prefix, so only the subtree the pattern
        // can actually reach has to be walked
        $slashPos = strrpos(substr($pattern, 0, strcspn($pattern, '*?[')), '/');
        $baseDir = false === $slashPos ? './' : substr($pattern, 0, $slashPos + 1);
        $relativePattern = false === $slashPos ? $pattern : substr($pattern, $slashPos + 1);

        $regex = '#^'.preg_quote($baseDir, '#').self::globToRegex($relativePattern).'$#';

        if (!is_dir($baseDir)) {
            return [];
        }

        try {
            // walking the subtree is only needed when the pattern itself nests
            $iterator = str_contains($relativePattern, '/')
                ? new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                    // an unreadable subdirectory skips that subtree instead of throwing
                    // all the way out of write()/close() and breaking logging
                    \RecursiveIteratorIterator::CATCH_GET_CHILD
                )
                : new \FilesystemIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $e) {
            // is_dir() above can pass on a directory we are still not allowed to open
            return [];
        }

        $logFiles = [];
        foreach ($iterator as $path => $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = self::normalizeDirectorySeparators((string) $path);
            if (1 === preg_match($regex, $path)) {
                $logFiles[] = $path;
            }
        }

        return $logFiles;
    }

    /**
     * Translates a glob pattern into a regex matching the same paths.
     *
     * Wildcards stop at "/" like glob's do, so a pattern for one directory
     * cannot start matching nested files once the walk goes deeper.
     */
    private static function globToRegex(string $glob): string
    {
        $regex = '';

        for ($i = 0, $length = \strlen($glob); $i < $length; $i++) {
            $char = $glob[$i];

            if ('*' === $char) {
                $regex .= '[^/]*';
                continue;
            }

            if ('?' === $char) {
                $regex .= '[^/]';
                continue;
            }

            // a "]" directly after the opening bracket is literal, hence $i + 2
            if ('[' === $char && false !== ($end = strpos($glob, ']', $i + 2))) {
                $class = substr($glob, $i + 1, $end - $i - 1);
                $negate = '';
                if (str_starts_with($class, '!')) {
                    $negate = '^';
                    $class = substr($class, 1);
                }
                $regex .= '['.$negate.str_replace(['\\', ']', '^'], ['\\\\', '\\]', '\\^'], $class).']';
                $i = $end;
                continue;
            }

            $regex .= preg_quote($char, '#');
        }

        return $regex;
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
            self::appendDirectorySeparator($fileInfo['dirname'] ?? '') . $this->filenameFormat
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
