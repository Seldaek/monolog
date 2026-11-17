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

use Closure;
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
    private Closure|null $previousExceptionHandler = null;

    /** @var array<class-string, LogLevel::*> an array of class name to LogLevel::* constant mapping */
    private array $uncaughtExceptionLevelMap = [];

    /** @var Closure|true|null */
    private Closure|bool|null $previousErrorHandler = null;

    /** @var array<int, LogLevel::*> an array of E_* constant to LogLevel::* constant mapping */
    private array $errorLevelMap = [];

    private bool $handleOnlyReportedErrors = true;

    private bool $hasFatalErrorHandler = false;

    /** @var int bitmask of E_* constants to capture stack traces for, 0 to disable */
    private int $captureStackTraceTypes = 0;

    private string $fatalLevel = LogLevel::ALERT;

    private string|null $reservedMemory = null;

    /** @var ?array{type: int, message: string, file: string, line: int, trace: mixed} */
    private array|null $lastFatalData = null;

    private const FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Registers a new ErrorHandler for a given Logger
     *
     * By default it will handle errors, exceptions and fatal errors
     *
     * @param  array<int, LogLevel::*>|false          $errorLevelMap     an array of E_* constant to LogLevel::* constant mapping, or false to disable error handling
     * @param  array<class-string, LogLevel::*>|false $exceptionLevelMap an array of class name to LogLevel::* constant mapping, or false to disable exception handling
     * @param  LogLevel::*|null|false                 $fatalLevel        a LogLevel::* constant, null to use the default LogLevel::ALERT or false to disable fatal error handling
     * @return static
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
        if ($callPrevious && null !== $prev) {
            $this->previousExceptionHandler = $prev(...);
        }

        return $this;
    }

    /**
     * @param  array<int, LogLevel::*> $levelMap an array of E_* constant to LogLevel::* constant mapping
     * @return $this
     */
    public function registerErrorHandler(array $levelMap = [], bool $callPrevious = true, int $errorTypes = -1, bool $handleOnlyReportedErrors = true): self
    {
        $prev = set_error_handler($this->handleError(...), $errorTypes);
        $this->errorLevelMap = array_replace($this->defaultErrorLevelMap(), $levelMap);
        if ($callPrevious) {
            $this->previousErrorHandler = $prev !== null ? $prev(...) : true;
        } else {
            $this->previousErrorHandler = null;
        }

        $this->handleOnlyReportedErrors = $handleOnlyReportedErrors;

        return $this;
    }

    /**
     * @param  LogLevel::*|null $level              a LogLevel::* constant, null to use the default LogLevel::ALERT
     * @param  int              $reservedMemorySize Amount of KBs to reserve in memory so that it can be freed when handling fatal errors giving Monolog some room in memory to get its job done
     * @return $this
     */
    public function registerFatalHandler($level = null, int $reservedMemorySize = 20): self
    {
        register_shutdown_function($this->handleFatalError(...));

        $this->reservedMemory = str_repeat(' ', 1024 * $reservedMemorySize);
        $this->fatalLevel = null === $level ? LogLevel::ALERT : $level;
        $this->hasFatalErrorHandler = true;

        return $this;
    }

    /**
     * Attaches an ErrorException carrying the stack trace of the error to the log record's context
     *
     * PHP does not report where a warning/notice/deprecation came from, so without this the records
     * only point at the line which raised the error, and not at its caller. Formatters rendering
     * stack traces (e.g. LineFormatter::includeStacktraces) then show the full call site.
     *
     * This is off by default because context.exception makes errors look like exceptions to the
     * handlers which treat that key specially, e.g. NewRelicHandler reports them via
     * newrelic_notice_error and RollbarHandler as exception occurrences, and because formatters
     * extending NormalizerFormatter then include the whole trace in every record. Narrowing
     * $errorTypes is wise if you log a lot of errors: E_DEPRECATED in particular can be very
     * frequent in legacy code while rarely being worth a stack trace.
     *
     * @param  int   $errorTypes bitmask of E_* constants to capture stack traces for
     * @return $this
     */
    public function captureStackTraces(bool $capture = true, int $errorTypes = E_ALL): self
    {
        $this->captureStackTraceTypes = $capture ? $errorTypes : 0;

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
            2048                => LogLevel::NOTICE, // E_STRICT
            E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_DEPRECATED        => LogLevel::NOTICE,
            E_USER_DEPRECATED   => LogLevel::NOTICE,
        ];
    }

    private function handleException(\Throwable $e): never
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

        if (null !== $this->previousExceptionHandler) {
            ($this->previousExceptionHandler)($e);
        }

        if (!headers_sent() && \in_array(strtolower((string) \ini_get('display_errors')), ['0', '', 'false', 'off', 'none', 'no'], true)) {
            // PHP 8.5+ warns if header('HTTP/...') already staged a status line, which is undetectable from userland
            @http_response_code(500);
        }

        exit(255);
    }

    private function handleError(int $code, string $message, string $file = '', int $line = 0): bool
    {
        if ($this->handleOnlyReportedErrors && 0 === (error_reporting() & $code)) {
            return false;
        }

        $isFatal = $this->hasFatalErrorHandler && \in_array($code, self::FATAL_ERRORS, true);

        $trace = null;
        if ($isFatal || 0 !== ($this->captureStackTraceTypes & $code)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace); // Exclude handleError from trace
        }

        // fatal error codes are ignored if a fatal error handler is present as well to avoid duplicate log entries
        if (!$isFatal) {
            $level = $this->errorLevelMap[$code] ?? LogLevel::CRITICAL;
            $context = ['code' => $code, 'message' => $message, 'file' => $file, 'line' => $line];
            if (null !== $trace) {
                $context['exception'] = self::createErrorException($code, $message, $file, $line, $trace);
            }
            $this->logger->log($level, self::codeToString($code).': '.$message, $context);
        } else {
            $this->lastFatalData = ['type' => $code, 'message' => $message, 'file' => $file, 'line' => $line, 'trace' => $trace];
        }

        if ($this->previousErrorHandler === true) {
            return false;
        }
        if ($this->previousErrorHandler instanceof Closure) {
            return (bool) ($this->previousErrorHandler)($code, $message, $file, $line);
        }

        return true;
    }

    /**
     * @private
     */
    public function handleFatalError(): void
    {
        $this->reservedMemory = '';

        if (\is_array($this->lastFatalData)) {
            $lastError = $this->lastFatalData;
        } else {
            $lastError = error_get_last();
        }
        if (\is_array($lastError) && \in_array($lastError['type'], self::FATAL_ERRORS, true)) {
            // PHP 8.5+ reports a trace for fatal errors which never reach the error handler (OOM, timeouts)
            $trace = $lastError['trace'] ?? null;
            $context = ['code' => $lastError['type'], 'message' => $lastError['message'], 'file' => $lastError['file'], 'line' => $lastError['line'], 'trace' => $trace];
            if (\is_array($trace)) {
                /** @var list<array<string, mixed>> $trace */
                $context['exception'] = self::createErrorException($lastError['type'], $lastError['message'], $lastError['file'], $lastError['line'], $trace);
            }
            $this->logger->log(
                $this->fatalLevel,
                'Fatal Error ('.self::codeToString($lastError['type']).'): '.$lastError['message'],
                $context
            );

            if ($this->logger instanceof Logger) {
                foreach ($this->logger->getHandlers() as $handler) {
                    $handler->close();
                }
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function createErrorException(int $code, string $message, string $file, int $line, array $trace): \ErrorException
    {
        $e = new \ErrorException($message, 0, $code, $file, $line);

        // Exception::getTrace() is final, so overwriting the private property is the only way to
        // report the real call site instead of ErrorHandler's own frame
        (new \ReflectionProperty(\Exception::class, 'trace'))->setValue($e, $trace);

        return $e;
    }

    private static function codeToString(int $code): string
    {
        return match ($code) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            2048 => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'Unknown PHP error',
        };
    }
}
