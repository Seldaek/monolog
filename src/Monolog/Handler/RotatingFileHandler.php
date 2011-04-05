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

/**
 * Stores logs to files that are rotated every day and a limited number of files are kept.
 *
 * This rotation is only intended to be used as a workaround. Using logrotate to
 * handle the rotation is strongly encouraged when you can use it.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class RotatingFileHandler extends StreamHandler
{
    protected $filename;
    protected $maxFiles;
    protected $mustRotate;

    /**
     * @param string $filename
     * @param integer $maxFiles The maximal amount of files to keep (0 means unlimited)
     * @param integer $level
     * @param Boolean $bubble
     */
    public function __construct($filename, $maxFiles = 0, $level = Logger::DEBUG, $bubble = true)
    {
        $this->filename = $filename;
        $this->maxFiles = (int) $maxFiles;


        $date = new \DateTime();
        $fileInfo = pathinfo($this->filename);
        $timedFilename = $fileInfo['dirname'].'/'.$fileInfo['filename'].'-'.$date->format('Y-m-d');
        if (!empty($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        // disable rotation upfront if files are unlimited
        if (0 === $this->maxFiles) {
            $this->mustRotate = false;
        }

        parent::__construct($timedFilename, $level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record)
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if (null === $this->mustRotate) {
            $this->mustRotate = !file_exists($this->url);
        }
        return parent::write($record);
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
     * Rotates the files.
     */
    protected function rotate()
    {
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

        // Sorting the files by name to rmeove the older ones
        $array = iterator_to_array($iterator);
        usort($array, function($a, $b) {
            return strcmp($a->getFilename(), $b->getFilename());
        });
        while ($count > $this->maxFiles) {
            $file = array_shift($array);
            /* @var $file \SplFileInfo */
            if ($file->isWritable()) {
                unlink($file->getRealPath());
            }
            $count--;
        }
    }
}