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

    protected static $levels = array(
        1 => 'DEBUG',
        5 => 'INFO',
        10 => 'WARN',
        15 => 'ERROR',
        20 => 'FATAL',
    );

    protected $logs;

    public function __construct($logs = array())
    {
        $this->logs = array();
        if (!is_array($logs)) {
            $logs = array($logs);
        }
        foreach ($logs as $log) {
            $this->logs[$log->getName()] = $log;
        }
    }

    public function addLog(Log $log)
    {
        $this->logs[$log->getName()] = $log;
    }

    public function addMessage($level, $message, $log = null)
    {
        if (null === $log) {
            $logs = $this->logs;
        } else {
            $logs = is_array($log) ? array_flip($log) : array($log => true);
        }
        foreach ($logs as $log => $dummy) {
            $this->logs[$log]->log($level, $message);
        }
    }

    public function addDebug($message, $log = null)
    {
        $this->addMessage(self::DEBUG, $message, $log);
    }

    public function addInfo($message, $log = null)
    {
        $this->addMessage(self::INFO, $message, $log);
    }

    public function addWarning($message, $log = null)
    {
        $this->addMessage(self::WARN, $message, $log);
    }

    public function addError($message, $log = null)
    {
        $this->addMessage(self::ERROR, $message, $log);
    }

    public static function getLevelName($level)
    {
        return self::$levels[$level];
    }

    // ZF Logger Compat

    public function debug($message, $log = null)
    {
        $this->addMessage(self::DEBUG, $message, $log);
    }

    public function info($message, $log = null)
    {
        $this->addMessage(self::INFO, $message, $log);
    }

    public function notice($message, $log = null)
    {
        $this->addMessage(self::INFO, $message, $log);
    }

    public function warn($message, $log = null)
    {
        $this->addMessage(self::WARN, $message, $log);
    }

    public function err($message, $log = null)
    {
        $this->addMessage(self::ERROR, $message, $log);
    }

    public function crit($message, $log = null)
    {
        $this->addMessage(self::ERROR, $message, $log);
    }

    public function alert($message, $log = null)
    {
        $this->addMessage(self::ERROR, $message, $log);
    }

    public function emerg($message, $log = null)
    {
        $this->addMessage(self::ERROR, $message, $log);
    }
}