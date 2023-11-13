<?php declare(strict_types=1);

namespace Monolog\Handler;

/*
 * This file is part of the Monolog package.
 *
 * (c) TheKing2 <theking2@king.ma>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
 * @author Johannes Kingma <theking2@king.ma>
 */
 */
abstract class AbstractRotatingFileHandler extends StreamHandler
{
    protected string $filename;
    protected bool|null $mustRotate = null;
    protected \DateTimeImmutable $nextRotation;

    /**
     * @param int      $maxFiles       The maximal amount of files to keep (0 means unlimited)
     * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool     $useLocking     Try to lock log file before doing any writes
     */
    public function __construct(string $filename, int|string|Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false)
    {
        $this->filename = Utils::canonicalizePath($filename);
        $this->nextRotation = $this->getNextRotation();

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

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if (null === $this->mustRotate) {
            $this->mustRotate = null === $this->url || !file_exists($this->url);
        }

        if ($this->nextRotation <= $record->datetime) {
            $this->mustRotate = true;
            $this->close();
        }

        parent::write($record);
    }

    /**
     * Rotates the files.
     */
    abstract protected function rotate(): void;
      
    /**
     * Get next rotation date/time
     *
     * @return DateTimeImmutable
     */
    abstract protected function getNextRotation(): \DateTimeImmutable;
}
