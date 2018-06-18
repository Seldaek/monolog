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
use Monolog\Handler\AbstractHandler;
use ReflectionExtension;

/**
 * Monolog error handler
 *
 * A facility to enable logging of runtime errors, exceptions, fatal errors and signals.
 *
 * Quick setup: <code>ErrorHandler::register($logger);</code>
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ErrorHandler
{
    private $logger;

    private $previousExceptionHandler;
    private $uncaughtExceptionLevel;

    private $previousErrorHandler;
    private $errorLevelMap;
    private $handleOnlyReportedErrors;

    private $hasFatalErrorHandler;
    private $fatalLevel;
    private $reservedMemory;
    private $lastFatalTrace;
    private static $fatalErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

    private $previousSignalHandler = array();
    private $signalLevelMap = array();
    private $signalRestartSyscalls = array();

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
     * @param  array|false     $errorLevelMap  an array of E_* constant to LogLevel::* constant mapping, or false to disable error handling
     * @param  int|false       $exceptionLevel a LogLevel::* constant, or false to disable exception handling
     * @param  int|false       $fatalLevel     a LogLevel::* constant, or false to disable fatal error handling
     * @return ErrorHandler
     */
    public static function register(LoggerInterface $logger, $errorLevelMap = array(), $exceptionLevel = null, $fatalLevel = null)
    {
        //Forces the autoloader to run for LogLevel. Fixes an autoload issue at compile-time on PHP5.3. See https://github.com/Seldaek/monolog/pull/929
        class_exists('\\Psr\\Log\\LogLevel', true);

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

    public function registerExceptionHandler($level = null, $callPrevious = true)
    {
        $prev = set_exception_handler(array($this, 'handleException'));
        $this->uncaughtExceptionLevel = $level;
        if ($callPrevious && $prev) {
            $this->previousExceptionHandler = $prev;
        }
    }

    public function registerErrorHandler(array $levelMap = array(), $callPrevious = true, $errorTypes = -1, $handleOnlyReportedErrors = true)
    {
        $prev = set_error_handler(array($this, 'handleError'), $errorTypes);
        $this->errorLevelMap = array_replace($this->defaultErrorLevelMap(), $levelMap);
        if ($callPrevious) {
            $this->previousErrorHandler = $prev ?: true;
        }

        $this->handleOnlyReportedErrors = $handleOnlyReportedErrors;
    }

    public function registerFatalHandler($level = null, $reservedMemorySize = 20)
    {
        register_shutdown_function(array($this, 'handleFatalError'));

        $this->reservedMemory = str_repeat(' ', 1024 * $reservedMemorySize);
        $this->fatalLevel = $level;
        $this->hasFatalErrorHandler = true;
    }

    public function registerSignalHandler($signo, $level = LogLevel::CRITICAL, $callPrevious = true, $restartSyscalls = true, $async = true)
    {
        if (!extension_loaded('pcntl') || !function_exists('pcntl_signal')) {
            return $this;
        }

        if ($callPrevious) {
            if (function_exists('pcntl_signal_get_handler')) {
                $handler = pcntl_signal_get_handler($signo);
                if ($handler === false) {
                    return $this;
                }
                $this->previousSignalHandler[$signo] = $handler;
            } else {
                $this->previousSignalHandler[$signo] = true;
            }
        } else {
            unset($this->previousSignalHandler[$signo]);
        }
        $this->signalLevelMap[$signo] = $level;
        $this->signalRestartSyscalls[$signo] = $restartSyscalls;

        if (function_exists('pcntl_async_signals') && $async !== null) {
            pcntl_async_signals($async);
        }

        pcntl_signal($signo, array($this, 'handleSignal'), $restartSyscalls);

        return $this;
    }

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
    public function handleException($e)
    {
        $this->logger->log(
            $this->uncaughtExceptionLevel === null ? LogLevel::ERROR : $this->uncaughtExceptionLevel,
            sprintf('Uncaught Exception %s: "%s" at %s line %s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
            array('exception' => $e)
        );

        if ($this->previousExceptionHandler) {
            call_user_func($this->previousExceptionHandler, $e);
        }

        exit(255);
    }

    /**
     * @private
     */
    public function handleError($code, $message, $file = '', $line = 0, $context = array())
    {
        if ($this->handleOnlyReportedErrors && !(error_reporting() & $code)) {
            return;
        }

        // fatal error codes are ignored if a fatal error handler is present as well to avoid duplicate log entries
        if (!$this->hasFatalErrorHandler || !in_array($code, self::$fatalErrors, true)) {
            $level = isset($this->errorLevelMap[$code]) ? $this->errorLevelMap[$code] : LogLevel::CRITICAL;
            $this->logger->log($level, self::codeToString($code).': '.$message, array('code' => $code, 'message' => $message, 'file' => $file, 'line' => $line));
        } else {
            // http://php.net/manual/en/function.debug-backtrace.php
            // As of 5.3.6, DEBUG_BACKTRACE_IGNORE_ARGS option was added.
            // Any version less than 5.3.6 must use the DEBUG_BACKTRACE_IGNORE_ARGS constant value '2'.
            $trace = debug_backtrace((PHP_VERSION_ID < 50306) ? 2 : DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace); // Exclude handleError from trace
            $this->lastFatalTrace = $trace;
        }

        if ($this->previousErrorHandler === true) {
            return false;
        } elseif ($this->previousErrorHandler) {
            return call_user_func($this->previousErrorHandler, $code, $message, $file, $line, $context);
        }
    }

    /**
     * @private
     */
    public function handleFatalError()
    {
        $this->reservedMemory = null;

        $lastError = error_get_last();
        if ($lastError && in_array($lastError['type'], self::$fatalErrors, true)) {
            $this->logger->log(
                $this->fatalLevel === null ? LogLevel::ALERT : $this->fatalLevel,
                'Fatal Error ('.self::codeToString($lastError['type']).'): '.$lastError['message'],
                array('code' => $lastError['type'], 'message' => $lastError['message'], 'file' => $lastError['file'], 'line' => $lastError['line'], 'trace' => $this->lastFatalTrace)
            );

            if ($this->logger instanceof Logger) {
                foreach ($this->logger->getHandlers() as $handler) {
                    if ($handler instanceof AbstractHandler) {
                        $handler->close();
                    }
                }
            }
        }
    }

    public function handleSignal($signo, array $siginfo = null)
    {
        static $signals = array();

        if (!$signals && extension_loaded('pcntl')) {
            $pcntl = new ReflectionExtension('pcntl');
            $constants = $pcntl->getConstants();
            if (!$constants) {
                // HHVM 3.24.2 returns an empty array.
                $constants = get_defined_constants(true);
                $constants = $constants['Core'];
            }
            foreach ($constants as $name => $value) {
                if (substr($name, 0, 3) === 'SIG' && $name[3] !== '_' && is_int($value)) {
                    $signals[$value] = $name;
                }
            }
            unset($constants);
        }

        $level = isset($this->signalLevelMap[$signo]) ? $this->signalLevelMap[$signo] : LogLevel::CRITICAL;
        $signal = isset($signals[$signo]) ? $signals[$signo] : $signo;
        $context = isset($siginfo) ? $siginfo : array();
        $this->logger->log($level, sprintf('Program received signal %s', $signal), $context);

        if (!isset($this->previousSignalHandler[$signo])) {
            return;
        }

        if ($this->previousSignalHandler[$signo] === true || $this->previousSignalHandler[$signo] === SIG_DFL) {
            if (extension_loaded('pcntl') && function_exists('pcntl_signal') && function_exists('pcntl_sigprocmask') && function_exists('pcntl_signal_dispatch')
                && extension_loaded('posix') && function_exists('posix_getpid') && function_exists('posix_kill')) {
                $restartSyscalls = isset($this->restartSyscalls[$signo]) ? $this->restartSyscalls[$signo] : true;
                pcntl_signal($signo, SIG_DFL, $restartSyscalls);
                pcntl_sigprocmask(SIG_UNBLOCK, array($signo), $oldset);
                posix_kill(posix_getpid(), $signo);
                pcntl_signal_dispatch();
                pcntl_sigprocmask(SIG_SETMASK, $oldset);
                pcntl_signal($signo, array($this, 'handleSignal'), $restartSyscalls);
            }
        } elseif (is_callable($this->previousSignalHandler[$signo])) {
            if (PHP_VERSION >= 71000) {
                $this->previousSignalHandler[$signo]($signo, $siginfo);
            } else {
                $this->previousSignalHandler[$signo]($signo);
            }
        }
    }

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
