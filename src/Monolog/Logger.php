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
use Throwable;

/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
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
     * @var string[] $levels Logging levels with the levels as key
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
     * On PHP7.0, generating microsecond resolution timestamps by calling
     * microtime(true), formatting the result via sprintf() and then parsing
     * the resulting string via \DateTime::createFromFormat() can incur
     * a measurable runtime overhead vs simple usage of DateTime to capture
     * a second resolution timestamp in systems which generate a large number
     * of log events.
     *
     * On PHP7.1 however microseconds are always included by the engine, so
     * this setting can be left alone unless you really want to suppress
     * microseconds in the output.
     *
     * @param bool $micro True to use microtime() to create timestamps
     */
    public function useMicrosecondTimestamps(bool $micro)
    {
        $this->microsecondTimestamps = $micro;
    }

    /**
     * Adds a log record.
     *
     * @param  int    $level   The logging level
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        // check if any handler will handle this message so we can return early and save cycles
        $handlerKey = null;
        foreach ($this->handlers as $key => $handler) {
            if ($handler->isHandling(['level' => $level])) {
                $handlerKey = $key;
                break;
            }
        }

        if (null === $handlerKey) {
            return false;
        }

        $levelName = static::getLevelName($level);

        $record = [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => $levelName,
            'channel' => $this->name,
            'datetime' => new DateTimeImmutable($this->microsecondTimestamps, $this->timezone),
            'extra' => [],
        ];

        try {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }

            // advance the array pointer to the first handler that will handle this record
            reset($this->handlers);
            while ($handlerKey !== key($this->handlers)) {
                next($this->handlers);
            }

            while ($handler = current($this->handlers)) {
                if (true === $handler->handle($record)) {
                    break;
                }

                next($this->handlers);
            }
        } catch (Throwable $e) {
            $this->handleException($e, $record);
        }

        return true;
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
     * @return array Assoc array with human-readable level names => level codes.
     */
    public static function getLevels(): array
    {
        return array_flip(static::$levels);
    }

    /**
     * Gets the name of the logging level.
     *
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
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
     */
    public static function toMonologLevel($level): int
    {
        if (is_string($level)) {
            if (defined(__CLASS__.'::'.strtoupper($level))) {
                return constant(__CLASS__.'::'.strtoupper($level));
            }

            throw new InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
        }

        if (!is_int($level)) {
            throw new InvalidArgumentException('Level "'.var_export($level, true).'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
        }

        return $level;
    }

    /**
     * Checks whether the Logger has a handler that listens on the given level
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
     * @param mixed  $level   The log level
     * @param string $message The log message
     * @param array  $context The log context
     */
    public function log($level, $message, array $context = []): void
    {
        $level = static::toMonologLevel($level);

        $this->addRecord($level, (string) $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     * @param string $message The log message
     * @param array  $context The log context
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
     */
    protected function handleException(Throwable $e, array $record)
    {
        if (!$this->exceptionHandler) {
            throw $e;
        }

        call_user_func($this->exceptionHandler, $e, $record);
    }
}
