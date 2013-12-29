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

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Monolog error handler
 *
 * A facility to enable logging of runtime errors, exceptions and fatal errors.
 *
 * Quick setup: <code>ErrorHandler::register($logger);</code>
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ErrorHandler
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \callable
     */
    private $previousExceptionHandler;

    /**
     * @var \callable
     */
    private $uncaughtExceptionLevel;

    /**
     * @var \callable
     */
    private $previousErrorHandler;

    /**
     * @var array
     */
    private $errorLevelMap;

    /**
     * @var integer
     */
    private $fatalLevel;

    /**
     * @var integer
     */
    private $reservedMemory;

    /**
     * @var array
     */
    private static $fatalErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Registers a new ErrorHandler for a given Logger
     *
     * By default it will handle errors, exceptions and fatal errors
     *
     * @param  LoggerInterface $logger
     * @param  array|false     $errorLevelMap  an array of E_* constant to LogLevel::* constant mapping, or false to
     *                                         disable error handling
     * @param  int|false       $exceptionLevel a LogLevel::* constant, or false to disable exception handling
     * @param  int|false       $fatalLevel     a LogLevel::* constant, or false to disable fatal error handling
     *
     * @return ErrorHandler
     */
    public static function register(
        LoggerInterface $logger,
        $errorLevelMap = array(),
        $exceptionLevel = null,
        $fatalLevel = null
    ) {
        $handler = new static($logger);
        if ($errorLevelMap !== false) {
            $handler->registerErrorHandler($errorLevelMap);
        }
        if ($exceptionLevel !== false) {
            $handler->registerExceptionHandler($exceptionLevel);
        }
        if ($fatalLevel !== false) {
            $handler->registerFatalHandler($fatalLevel);
        }

        return $handler;
    }

    /**
     * @param integer $level
     * @param bool    $callPrevious
     */
    public function registerExceptionHandler($level = null, $callPrevious = true)
    {
        $prev                         = set_exception_handler(array($this, 'handleException'));
        $this->uncaughtExceptionLevel = $level;
        if ($callPrevious && $prev) {
            $this->previousExceptionHandler = $prev;
        }
    }

    /**
     * @param array   $levelMap
     * @param bool    $callPrevious
     * @param integer $errorTypes
     */
    public function registerErrorHandler(array $levelMap = array(), $callPrevious = true, $errorTypes = -1)
    {
        $prev                = set_error_handler(array($this, 'handleError'), $errorTypes);
        $this->errorLevelMap = array_replace($this->defaultErrorLevelMap(), $levelMap);
        if ($callPrevious) {
            $this->previousErrorHandler = $prev ?: true;
        }
    }

    /**
     * @param integer $level
     * @param int     $reservedMemorySize
     */
    public function registerFatalHandler($level = null, $reservedMemorySize = 20)
    {
        register_shutdown_function(array($this, 'handleFatalError'));

        $this->reservedMemory = str_repeat(' ', 1024 * $reservedMemorySize);
        $this->fatalLevel     = $level;
    }

    /**
     * @return array
     */
    protected function defaultErrorLevelMap()
    {
        return array(
            E_ERROR             => LogLevel::CRITICAL,
            E_WARNING           => LogLevel::WARNING,
            E_PARSE             => LogLevel::ALERT,
            E_NOTICE            => LogLevel::NOTICE,
            E_CORE_ERROR        => LogLevel::CRITICAL,
            E_CORE_WARNING      => LogLevel::WARNING,
            E_COMPILE_ERROR     => LogLevel::ALERT,
            E_COMPILE_WARNING   => LogLevel::WARNING,
            E_USER_ERROR        => LogLevel::ERROR,
            E_USER_WARNING      => LogLevel::WARNING,
            E_USER_NOTICE       => LogLevel::NOTICE,
            E_STRICT            => LogLevel::NOTICE,
            E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_DEPRECATED        => LogLevel::NOTICE,
            E_USER_DEPRECATED   => LogLevel::NOTICE,
        );
    }

    /**
     * @private
     */
    public function handleException(\Exception $exception)
    {
        $this->logger->log(
            $this->uncaughtExceptionLevel === null ? LogLevel::ERROR : $this->uncaughtExceptionLevel,
            'Uncaught exception',
            array('exception' => $exception)
        );

        if ($this->previousExceptionHandler) {
            call_user_func($this->previousExceptionHandler, $exception);
        }
    }

    /**
     * @private
     *
     * @param integer $code
     * @param string  $message
     * @param string  $file
     * @param integer $line
     * @param array   $context
     *
     * @return null|boolean
     */
    public function handleError($code, $message, $file = '', $line = 0, $context = array())
    {
        if (!(error_reporting() & $code)) {
            return null;
        }

        $level = isset($this->errorLevelMap[$code]) ? $this->errorLevelMap[$code] : LogLevel::CRITICAL;
        $this->logger->log(
            $level,
            self::codeToString($code) . ': ' . $message,
            array('file' => $file, 'line' => $line)
        );

        if ($this->previousErrorHandler === true) {
            return false;
        } elseif ($this->previousErrorHandler) {
            return call_user_func($this->previousErrorHandler, $code, $message, $file, $line, $context);
        }

        return null;
    }

    /**
     * @private
     */
    public function handleFatalError()
    {
        $this->reservedMemory = null;

        $lastError = error_get_last();
        if ($lastError && in_array($lastError['type'], self::$fatalErrors)) {
            $this->logger->log(
                $this->fatalLevel === null ? LogLevel::ALERT : $this->fatalLevel,
                'Fatal Error (' . self::codeToString($lastError['type']) . '): ' . $lastError['message'],
                array('file' => $lastError['file'], 'line' => $lastError['line'])
            );
        }
    }

    /**
     * @param integer $code
     *
     * @return string
     */
    private static function codeToString($code)
    {
        switch ($code) {
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
        }

        return 'Unknown PHP error';
    }
}
