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

use DateTimeZone;
use Monolog\Handler\HandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Throwable;
use Stringable;

/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-type Level Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY
 * @phpstan-type LevelName 'DEBUG'|'INFO'|'NOTICE'|'WARNING'|'ERROR'|'CRITICAL'|'ALERT'|'EMERGENCY'
 * @phpstan-type Record array{message: string, context: mixed[], level: Level, level_name: LevelName, channel: string, datetime: \DateTimeImmutable, extra: mixed[]}
 */
class Logger implements LoggerInterface, ResettableInterface
{
    /**
     * Detailed debug information
     */
    public const DEBUG = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    public const INFO = 200;

    /**
     * Uncommon events
     */
    public const NOTICE = 250;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public const WARNING = 300;

    /**
     * Runtime errors
     */
    public const ERROR = 400;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public const CRITICAL = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public const ALERT = 550;

    /**
     * Urgent alert.
     */
    public const EMERGENCY = 600;

    /**
     * Monolog API version
     *
     * This is only bumped when API breaks are done and should
     * follow the major version of the library
     *
     * @var int
     */
    public const API = 2;

    /**
     * This is a static variable and not a constant to serve as an extension point for custom levels
     *
     * @var array<int, string> $levels Logging levels with the levels as key
     *
     * @phpstan-var array<Level, LevelName> $levels Logging levels with the levels as key
     */
    protected static $levels = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    /**
     * Mapping between levels numbers defined in RFC 5424 and Monolog ones
     *
     * @phpstan-var array<int, Level> $rfc_5424_levels
     */
    private const RFC_5424_LEVELS = [
        7 => self::DEBUG,
        6 => self::INFO,
        5 => self::NOTICE,
        4 => self::WARNING,
        3 => self::ERROR,
        2 => self::CRITICAL,
        1 => self::ALERT,
        0 => self::EMERGENCY,
    ];

    /**
     * @var string
     */
    protected $name;

    /**
     * The handler stack
     *
     * @var HandlerInterface[]
     */
    protected $handlers;

    /**
     * Processors that will process all log records
     *
     * To process records of a single handler instead, add the processor on that specific handler
     *
     * @var callable[]
     */
    protected $processors;

    /**
     * @var bool
     */
    protected $microsecondTimestamps = true;

    /**
     * @var DateTimeZone
     */
    protected $timezone;

    /**
     * @var callable|null
     */
    protected $exceptionHandler;

    /**
     * @var int Keeps track of depth to prevent infinite logging loops
     */
    private $logDepth = 0;

    /**
     * @var bool Whether to detect infinite logging loops
     *
     * This can be disabled via {@see useLoggingLoopDetection} if you have async handlers that do not play well with this
     */
    private $detectCycles = true;

    /**
     * @psalm-param array<callable(array): array> $processors
     *
     * @param string             $name       The logging channel, a simple descriptive name that is attached to all log records
     * @param HandlerInterface[] $handlers   Optional stack of handlers, the first one in the array is called first, etc.
     * @param callable[]         $processors Optional array of processors
     * @param DateTimeZone|null  $timezone   Optional timezone, if not provided date_default_timezone_get() will be used
     */
    public function __construct(string $name, array $handlers = [], array $processors = [], ?DateTimeZone $timezone = null)
    {
        $this->name = $name;
        $this->setHandlers($handlers);
        $this->processors = $processors;
        $this->timezone = $timezone ?: new DateTimeZone(date_default_timezone_get() ?: 'UTC');
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return a new cloned instance with the name changed
     */
    public function withName(string $name): self
    {
        $new = clone $this;
        $new->name = $name;

        return $new;
    }

    /**
     * Pushes a handler on to the stack.
     */
    public function pushHandler(HandlerInterface $handler): self
    {
        array_unshift($this->handlers, $handler);

        return $this;
    }

    /**
     * Pops a handler from the stack
     *
     * @throws \LogicException If empty handler stack
     */
    public function popHandler(): HandlerInterface
    {
        if (!$this->handlers) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }

        return array_shift($this->handlers);
    }

    /**
     * Set handlers, replacing all existing ones.
     *
     * If a map is passed, keys will be ignored.
     *
     * @param HandlerInterface[] $handlers
     */
    public function setHandlers(array $handlers): self
    {
        $this->handlers = [];
        foreach (array_reverse($handlers) as $handler) {
            $this->pushHandler($handler);
        }

        return $this;
    }

    /**
     * @return HandlerInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Adds a processor on to the stack.
     */
    public function pushProcessor(callable $callback): self
    {
        array_unshift($this->processors, $callback);

        return $this;
    }

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @throws \LogicException If empty processor stack
     * @return callable
     */
    public function popProcessor(): callable
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    /**
     * @return callable[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }

    /**
     * Control the use of microsecond resolution timestamps in the 'datetime'
     * member of new records.
     *
     * As of PHP7.1 microseconds are always included by the engine, so
     * there is no performance penalty and Monolog 2 enabled microseconds
     * by default. This function lets you disable them though in case you want
     * to suppress microseconds from the output.
     *
     * @param bool $micro True to use microtime() to create timestamps
     */
    public function useMicrosecondTimestamps(bool $micro): self
    {
        $this->microsecondTimestamps = $micro;

        return $this;
    }

    public function useLoggingLoopDetection(bool $detectCycles): self
    {
        $this->detectCycles = $detectCycles;

        return $this;
    }

    /**
     * Adds a log record.
     *
     * @param  int               $level    The logging level (a Monolog or RFC 5424 level)
     * @param  string            $message  The log message
     * @param  mixed[]           $context  The log context
     * @param  DateTimeImmutable $datetime Optional log date to log into the past or future
     * @return bool              Whether the record has been processed
     *
     * @phpstan-param Level $level
     */
    public function addRecord(int $level, string $message, array $context = [], DateTimeImmutable $datetime = null): bool
    {
        if (isset(self::RFC_5424_LEVELS[$level])) {
            $level = self::RFC_5424_LEVELS[$level];
        }

        if ($this->detectCycles) {
            $this->logDepth += 1;
        }
        if ($this->logDepth === 3) {
            $this->warning('A possible infinite logging loop was detected and aborted. It appears some of your handler code is triggering logging, see the previous log record for a hint as to what may be the cause.');
            return false;
        } elseif ($this->logDepth >= 5) { // log depth 4 is let through so we can log the warning above
            return false;
        }

        try {
            $record = null;

            foreach ($this->handlers as $handler) {
                if (null === $record) {
                    // skip creating the record as long as no handler is going to handle it
                    if (!$handler->isHandling(['level' => $level])) {
                        continue;
                    }

                    $levelName = static::getLevelName($level);

                    $record = [
                        'message' => $message,
                        'context' => $context,
                        'level' => $level,
                        'level_name' => $levelName,
                        'channel' => $this->name,
                        'datetime' => $datetime ?? new DateTimeImmutable($this->microsecondTimestamps, $this->timezone),
                        'extra' => [],
                    ];

                    try {
                        foreach ($this->processors as $processor) {
                            $record = $processor($record);
                        }
                    } catch (Throwable $e) {
                        $this->handleException($e, $record);

                        return true;
                    }
                }

                // once the record exists, send it to all handlers as long as the bubbling chain is not interrupted
                try {
                    if (true === $handler->handle($record)) {
                        break;
                    }
                } catch (Throwable $e) {
                    $this->handleException($e, $record);

                    return true;
                }
            }
        } finally {
            if ($this->detectCycles) {
                $this->logDepth--;
            }
        }

        return null !== $record;
    }

    /**
     * Ends a log cycle and frees all resources used by handlers.
     *
     * Closing a Handler means flushing all buffers and freeing any open resources/handles.
     * Handlers that have been closed should be able to accept log records again and re-open
     * themselves on demand, but this may not always be possible depending on implementation.
     *
     * This is useful at the end of a request and will be called automatically on every handler
     * when they get destructed.
     */
    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }

    /**
     * Ends a log cycle and resets all handlers and processors to their initial state.
     *
     * Resetting a Handler or a Processor means flushing/cleaning all buffers, resetting internal
     * state, and getting it back to a state in which it can receive log records again.
     *
     * This is useful in case you want to avoid logs leaking between two requests or jobs when you
     * have a long running process like a worker or an application server serving multiple requests
     * in one process.
     */
    public function reset(): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ResettableInterface) {
                $handler->reset();
            }
        }

        foreach ($this->processors as $processor) {
            if ($processor instanceof ResettableInterface) {
                $processor->reset();
            }
        }
    }

    /**
     * Gets all supported logging levels.
     *
     * @return array<string, int> Assoc array with human-readable level names => level codes.
     * @phpstan-return array<LevelName, Level>
     */
    public static function getLevels(): array
    {
        return array_flip(static::$levels);
    }

    /**
     * Gets the name of the logging level.
     *
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
     *
     * @phpstan-param  Level     $level
     * @phpstan-return LevelName
     */
    public static function getLevelName(int $level): string
    {
        if (!isset(static::$levels[$level])) {
            throw new InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
        }

        return static::$levels[$level];
    }

    /**
     * Converts PSR-3 levels to Monolog ones if necessary
     *
     * @param  string|int                        $level Level number (monolog) or name (PSR-3)
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
     *
     * @phpstan-param  Level|LevelName|LogLevel::* $level
     * @phpstan-return Level
     */
    public static function toMonologLevel($level): int
    {
        if (is_string($level)) {
            if (is_numeric($level)) {
                /** @phpstan-ignore-next-line */
                return intval($level);
            }

            // Contains chars of all log levels and avoids using strtoupper() which may have
            // strange results depending on locale (for example, "i" will become "İ" in Turkish locale)
            $upper = strtr($level, 'abcdefgilmnortuwy', 'ABCDEFGILMNORTUWY');
            if (defined(__CLASS__.'::'.$upper)) {
                return constant(__CLASS__ . '::' . $upper);
            }

            throw new InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels) + static::$levels));
        }

        if (!is_int($level)) {
            throw new InvalidArgumentException('Level "'.var_export($level, true).'" is not defined, use one of: '.implode(', ', array_keys(static::$levels) + static::$levels));
        }

        return $level;
    }

    /**
     * Checks whether the Logger has a handler that listens on the given level
     *
     * @phpstan-param Level $level
     */
    public function isHandling(int $level): bool
    {
        $record = [
            'level' => $level,
        ];

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set a custom exception handler that will be called if adding a new record fails
     *
     * The callable will receive an exception object and the record that failed to be logged
     */
    public function setExceptionHandler(?callable $callback): self
    {
        $this->exceptionHandler = $callback;

        return $this;
    }

    public function getExceptionHandler(): ?callable
    {
        return $this->exceptionHandler;
    }

    /**
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed             $level   The log level (a Monolog, PSR-3 or RFC 5424 level)
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     *
     * @phpstan-param Level|LevelName|LogLevel::* $level
     */
    public function log($level, $message, array $context = []): void
    {
        if (!is_int($level) && !is_string($level)) {
            throw new \InvalidArgumentException('$level is expected to be a string or int');
        }

        if (isset(self::RFC_5424_LEVELS[$level])) {
            $level = self::RFC_5424_LEVELS[$level];
        }

        $level = static::toMonologLevel($level);

        $this->addRecord($level, (string) $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function debug($message, array $context = []): void
    {
        $this->addRecord(static::DEBUG, (string) $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function info($message, array $context = []): void
    {
        $this->addRecord(static::INFO, (string) $message, $context);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function notice($message, array $context = []): void
    {
        $this->addRecord(static::NOTICE, (string) $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function warning($message, array $context = []): void
    {
        $this->addRecord(static::WARNING, (string) $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function error($message, array $context = []): void
    {
        $this->addRecord(static::ERROR, (string) $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function critical($message, array $context = []): void
    {
        $this->addRecord(static::CRITICAL, (string) $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function alert($message, array $context = []): void
    {
        $this->addRecord(static::ALERT, (string) $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function emergency($message, array $context = []): void
    {
        $this->addRecord(static::EMERGENCY, (string) $message, $context);
    }

    /**
     * Sets the timezone to be used for the timestamp of log records.
     */
    public function setTimezone(DateTimeZone $tz): self
    {
        $this->timezone = $tz;

        return $this;
    }

    /**
     * Returns the timezone to be used for the timestamp of log records.
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Delegates exception management to the custom exception handler,
     * or throws the exception if no custom handler is set.
     *
     * @param array $record
     * @phpstan-param Record $record
     */
    protected function handleException(Throwable $e, array $record): void
    {
        if (!$this->exceptionHandler) {
            throw $e;
        }

        ($this->exceptionHandler)($e, $record);
    }
}
