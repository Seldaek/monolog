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
    /**
     * Debug messages
     */
    const DEBUG   = 100;

    /**
     * Messages you usually don't want to see
     */
    const INFO    = 200;

    /**
     * Exceptional occurences that are not errors
     *
     * This is typically the logging level you want to use
     */
    const WARNING = 300;

    /**
     * Errors
     */
    const ERROR   = 400;

    protected static $levels = array(
        100 => 'DEBUG',
        200 => 'INFO',
        300 => 'WARNING',
        400 => 'ERROR',
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
        $this->addMessage(self::WARNING, $message, $log);
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
        $this->addMessage(self::WARNING, $message, $log);
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