<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

class Logger
{
    const DEBUG = 1;
    const INFO = 5;
    const WARN = 10;
    const ERROR = 15;
    const FATAL = 20;

    protected $logs;

    public function __construct($logs = array())
    {
        $this->logs = $logs;
    }

    public function addLog(Log $log)
    {
        $this->logs[$log->getName()] = $log;
    }

    public function log($level, $message, $log = null)
    {
        if (null === $log) {
            $logs = $this->logs;
        } else {
            $logs = (array) $log;
        }
        foreach ($logs as $log => $dummy) {
            $this->logs[$log]->log($level, $message);
        }
    }

    public function debug($message, $log = null)
    {
        $this->log(self::DEBUG, $message, $log);
    }

    public function info($message, $log = null)
    {
        $this->log(self::INFO, $message, $log);
    }

    public function warn($message, $log = null)
    {
        $this->log(self::WARN, $message, $log);
    }

    public function error($message, $log = null)
    {
        $this->log(self::ERROR, $message, $log);
    }

    public function fatal($message, $log = null)
    {
        $this->log(self::FATAL, $message, $log);
    }
}