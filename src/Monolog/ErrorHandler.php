<?php declare(strict_types=1);

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
    /** @var LoggerInterface */
    private $logger;

    /** @var ?callable */
    private $previousExceptionHandler = null;
    /** @var array<class-string, LogLevel::*> an array of class name to LogLevel::* constant mapping */
    private $uncaughtExceptionLevelMap = [];

    /** @var callable|true|null */
    private $previousErrorHandler = null;
    /** @var array<int, LogLevel::*> an array of E_* constant to LogLevel::* constant mapping */
    private $errorLevelMap = [];
    /** @var bool */
    private $handleOnlyReportedErrors = true;

    /** @var bool */
    private $hasFatalErrorHandler = false;
    /** @var LogLevel::* */
    private $fatalLevel = LogLevel::ALERT;
    /** @var ?string */
    private $reservedMemory = null;
    /** @var ?array{type: int, message: string, file: string, line: int, trace: mixed} */
    private $lastFatalData = null;
    /** @var int[] */
    private static $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Registers a new ErrorHandler for a given Logger
     *
     * By default it will handle errors, exceptions and fatal errors
     *
     * @param  LoggerInterface                        $logger
     * @param  array<int, LogLevel::*>|false          $errorLevelMap     an array of E_* constant to LogLevel::* constant mapping, or false to disable error handling
     * @param  array<class-string, LogLevel::*>|false $exceptionLevelMap an array of class name to LogLevel::* constant mapping, or false to disable exception handling
     * @param  LogLevel::*|null|false                 $fatalLevel        a LogLevel::* constant, null to use the default LogLevel::ALERT or false to disable fatal error handling
     * @return ErrorHandler
     */
    public static function register(LoggerInterface $logger, $errorLevelMap = [], $exceptionLevelMap = [], $fatalLevel = null): self
    {
        /** @phpstan-ignore-next-line */
        $handler = new static($logger);
        if ($errorLevelMap !== false) {
            $handler->registerErrorHandler($errorLevelMap);
        }
        if ($exceptionLevelMap !== false) {
            $handler->registerExceptionHandler($exceptionLevelMap);
        }
        if ($fatalLevel !== false) {
            $handler->registerFatalHandler($fatalLevel);
        }

        return $handler;
    }

    /**
     * @param  array<class-string, LogLevel::*> $levelMap an array of class name to LogLevel::* constant mapping
     * @return $this
     */
    public function registerExceptionHandler(array $levelMap = [], bool $callPrevious = true): self
    {
        $prev = set_exception_handler(function (\Throwable $e): void {
            $this->handleException($e);
        });
        $this->uncaughtExceptionLevelMap = $levelMap;
        foreach ($this->defaultExceptionLevelMap() as $class => $level) {
            if (!isset($this->uncaughtExceptionLevelMap[$class])) {
                $this->uncaughtExceptionLevelMap[$class] = $level;
            }
        }
        if ($callPrevious && $prev) {
            $this->previousExceptionHandler = $prev;
        }

        return $this;
    }

    /**
     * @param  array<int, LogLevel::*> $levelMap an array of E_* constant to LogLevel::* constant mapping
     * @return $this
     */
    public function registerErrorHandler(array $levelMap = [], bool $callPrevious = true, int $errorTypes = -1, bool $handleOnlyReportedErrors = true): self
    {
        $prev = set_error_handler([$this, 'handleError'], $errorTypes);
        $this->errorLevelMap = array_replace($this->defaultErrorLevelMap(), $levelMap);
        if ($callPrevious) {
            $this->previousErrorHandler = $prev ?: true;
        } else {
            $this->previousErrorHandler = null;
        }

        $this->handleOnlyReportedErrors = $handleOnlyReportedErrors;

        return $this;
    }

    /**
     * @param LogLevel::*|null $level              a LogLevel::* constant, null to use the default LogLevel::ALERT
     * @param int              $reservedMemorySize Amount of KBs to reserve in memory so that it can be freed when handling fatal errors giving Monolog some room in memory to get its job done
     */
    public function registerFatalHandler($level = null, int $reservedMemorySize = 20): self
    {
        register_shutdown_function([$this, 'handleFatalError']);

        $this->reservedMemory = str_repeat(' ', 1024 * $reservedMemorySize);
        $this->fatalLevel = null === $level ? LogLevel::ALERT : $level;
        $this->hasFatalErrorHandler = true;

        return $this;
    }

    /**
     * @return array<class-string, LogLevel::*>
     */
    protected function defaultExceptionLevelMap(): array
    {
        return [
            'ParseError' => LogLevel::CRITICAL,
            'Throwable' => LogLevel::ERROR,
        ];
    }

    /**
     * @return array<int, LogLevel::*>
     */
    protected function defaultErrorLevelMap(): array
    {
        return [
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
            2048                => LogLevel::NOTICE,  // E_STRICT
            E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_DEPRECATED        => LogLevel::NOTICE,
            E_USER_DEPRECATED   => LogLevel::NOTICE,
        ];
    }

    /**
     * @phpstan-return never
     */
    private function handleException(\Throwable $e): void
    {
        $level = LogLevel::ERROR;
        foreach ($this->uncaughtExceptionLevelMap as $class => $candidate) {
            if ($e instanceof $class) {
                $level = $candidate;
                break;
            }
        }

        $this->logger->log(
            $level,
            sprintf('Uncaught Exception %s: "%s" at %s line %s', Utils::getClass($e), $e->getMessage(), $e->getFile(), $e->getLine()),
            ['exception' => $e]
        );

        if ($this->previousExceptionHandler) {
            ($this->previousExceptionHandler)($e);
        }

        if (!headers_sent() && in_array(strtolower((string) ini_get('display_errors')), ['0', '', 'false', 'off', 'none', 'no'], true)) {
            http_response_code(500);
        }

        exit(255);
    }

    /**
     * @private
     *
     * @param mixed[] $context
     */
    public function handleError(int $code, string $message, string $file = '', int $line = 0, ?array $context = []): bool
    {
        if ($this->handleOnlyReportedErrors && !(error_reporting() & $code)) {
            return false;
        }

        // fatal error codes are ignored if a fatal error handler is present as well to avoid duplicate log entries
        if (!$this->hasFatalErrorHandler || !in_array($code, self::$fatalErrors, true)) {
            $level = $this->errorLevelMap[$code] ?? LogLevel::CRITICAL;
            $this->logger->log($level, self::codeToString($code).': '.$message, ['code' => $code, 'message' => $message, 'file' => $file, 'line' => $line]);
        } else {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace); // Exclude handleError from trace
            $this->lastFatalData = ['type' => $code, 'message' => $message, 'file' => $file, 'line' => $line, 'trace' => $trace];
        }

        if ($this->previousErrorHandler === true) {
            return false;
        } elseif ($this->previousErrorHandler) {
            return (bool) ($this->previousErrorHandler)($code, $message, $file, $line, $context);
        }

        return true;
    }

    /**
     * @private
     */
    public function handleFatalError(): void
    {
        $this->reservedMemory = '';

        if (is_array($this->lastFatalData)) {
            $lastError = $this->lastFatalData;
        } else {
            $lastError = error_get_last();
        }

        if ($lastError && in_array($lastError['type'], self::$fatalErrors, true)) {
            $trace = $lastError['trace'] ?? null;
            $this->logger->log(
                $this->fatalLevel,
                'Fatal Error ('.self::codeToString($lastError['type']).'): '.$lastError['message'],
                ['code' => $lastError['type'], 'message' => $lastError['message'], 'file' => $lastError['file'], 'line' => $lastError['line'], 'trace' => $trace]
            );

            if ($this->logger instanceof Logger) {
                foreach ($this->logger->getHandlers() as $handler) {
                    $handler->close();
                }
            }
        }
    }

    /**
     * @param int $code
     */
    private static function codeToString($code): string
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
            case 2048:
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
