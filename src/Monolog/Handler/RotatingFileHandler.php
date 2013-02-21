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
    protected $filename;
    protected $maxFiles;
    protected $mustRotate;
    protected $nextRotation;

    /**
     * @param string  $filename
     * @param integer $maxFiles The maximal amount of files to keep (0 means unlimited)
     * @param integer $level    The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble   Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($filename, $maxFiles = 0, $level = Logger::DEBUG, $bubble = true)
    {
        $this->filename = $filename;
        $this->maxFiles = (int) $maxFiles;
        $this->nextRotation = new \DateTime('tomorrow');

        parent::__construct($this->getTimedFilename(), $level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        parent::close();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if (null === $this->mustRotate) {
            $this->mustRotate = !file_exists($this->url);
        }

        if ($this->nextRotation < $record['datetime']) {
            $this->mustRotate = true;
            $this->close();
        }

        parent::write($record);
    }

    /**
     * Rotates the files.
     */
    protected function rotate()
    {
        // update filename
        $this->url = $this->getTimedFilename();
        $this->nextRotation = new \DateTime('tomorrow');

        // skip GC of old logs if files are unlimited
        if (0 === $this->maxFiles) {
            return;
        }

        $fileInfo = pathinfo($this->filename);
        $glob = $fileInfo['dirname'].'/'.$fileInfo['filename'].'-*';
        if (!empty($fileInfo['extension'])) {
            $glob .= '.'.$fileInfo['extension'];
        }
        $iterator = new \GlobIterator($glob);
        $count = $iterator->count();
        if ($this->maxFiles >= $count) {
            // no files to remove
            return;
        }

        // Sorting the files by name to remove the older ones
        $array = iterator_to_array($iterator);
        usort($array, function($a, $b) {
            return strcmp($b->getFilename(), $a->getFilename());
        });

        foreach (array_slice($array, $this->maxFiles) as $file) {
            if ($file->isWritable()) {
                unlink($file->getRealPath());
            }
        }
    }

    protected function getTimedFilename()
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = $fileInfo['dirname'].'/'.$fileInfo['filename'].'-'.date('Y-m-d');
        if (!empty($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        return $timedFilename;
    }
}
