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
use LogicException;
use Monolog\Handler\HandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;

/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Logger implements LoggerInterface
{
    /**
     * Detailed debug information.
     *
     * @var int
     */
    const DEBUG = 100;

    /**
     * Interesting events.
     *
     * Examples: User logs in, SQL logs.
     *
     * @var int
     */
    const INFO = 200;

    /**
     * Uncommon events.
     *
     * @var int
     */
    const NOTICE = 250;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     *
     * @var int
     */
    const WARNING = 300;

    /**
     * Runtime errors.
     *
     * @var int
     */
    const ERROR = 400;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @var int
     */
    const CRITICAL = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     *
     * @var int
     */
    const ALERT = 550;

    /**
     * Urgent alert.
     *
     * @var int
     */
    const EMERGENCY = 600;

    /**
     * Monolog API version.
     *
     * This is only bumped when API breaks are done and should
     * follow the major version of the library.
     *
     * @var int
     */
    const API = 2;

    /**
     * Logging levels from syslog protocol defined in RFC 5424.
     *
     * This is a static variable and not a constant to serve as an extension point for custom levels.
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
     * The logging channel.
     *
     * @var string
     */
    protected $name;

    /**
     * The handlers stack.
     *
     * @var HandlerInterface[]
     */
    protected $handlers = [];

    /**
     * Processors that will process all log records.
     *
     * To process records of a single handler instead, add the processor on that specific handler.
     *
     * @var callable[]
     */
    protected $processors = [];

    /**
     * Detect that the logger should use microtime() to create a timestamp.
     *
     * @var bool
     */
    protected $microsecondTimestamps = true;

    /**
     * The DateTimeZone instance.
     *
     * @var DateTimeZone
     */
    protected $timezone;

    /**
     * Create a new Logger instance.
     *
     * @param string             $name       The logging channel, a simple descriptive name that is attached to all log records
     * @param HandlerInterface[] $handlers   Optional stack of handlers, the first one in the array is called first, etc.
     * @param callable[]         $processors Optional array of processors
     * @param DateTimeZone       $timezone   Optional timezone, if not provided date_default_timezone_get() will be used
     */
    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = [],
        DateTimeZone $timezone = null
    ) {
        $this->name = $name;
        $this->handlers = $handlers;
        $this->processors = $processors;
        $this->timezone = $timezone ?: $this->getDefaultDateTimeZone();
    }

    /**
     * Get the default DateTimeZone instance with UTC.
     *
     * @return \DateTimeZone
     */
    protected function getDefaultDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get() ?: 'UTC');
    }

    /**
     * Get the logging channel.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return a new cloned instance with the name changed
     *
     * @param string $name The logging channel
     * @return self
     */
    public function withName(string $name): self
    {
        $new = clone $this;
        $new->name = $name;

        return $new;
    }

    /**
     * Pushes a handler on to the stack.
     *
     * @param \Monolog\Handler\HandlerInterface $handler The log handler
     * @return self
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
        if (empty($this->handlers)) {
            throw new LogicException('You tried to pop from an empty handler stack.');
        }

        return array_shift($this->handlers);
    }

    /**
     * Set handlers, replacing all existing ones.
     *
     * If a map is passed, keys will be ignored.
     *
     * @param HandlerInterface[] $handlers The log handlers stack
     * @return self
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
     * Get handlers stack.
     *
     * @return HandlerInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Adds a processor on to the stack.
     *
     * @param callable $callback The log processor
     * @return self
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
        if (empty($this->processors)) {
            throw new LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    /**
     * Get the processors stack.
     *
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
     * @return void
     */
    public function useMicrosecondTimestamps(bool $micro)
    {
        $this->microsecondTimestamps = $micro;
    }

    /**
     * Adds a log record.
     *
     * @param  int     $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return bool Whether the record has been processed
     */
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        // Check if any handler will handle this message so we can return early and save cycles.
        $handlerKey = $this->findHandlerKey($level);

        if (null === $handlerKey) {
            return false;
        }

        $record = $this->makeRecord($level, $message, $context);

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

        return true;
    }

    /**
     * Find the handler key by given level.
     *
     * @param int $level The log level
     * @return mixed
     */
    protected function findHandlerKey(int $level)
    {
        foreach ($this->handlers as $key => $handler) {
            if ($handler->isHandling(['level' => $level])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Make a new log record from given information.
     *
     * @param  int     $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return array
     */
    protected function makeRecord(int $level, string $message, array $context = []): array
    {
        $levelName = static::getLevelName($level);

        return [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => $levelName,
            'channel' => $this->name,
            'datetime' => new DateTimeImmutable($this->microsecondTimestamps, $this->timezone),
            'extra' => [],
        ];
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
     * @param  int                               $level
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
     * @return string
     */
    public static function getLevelName(int $level): string
    {
        if (!isset(static::$levels[$level])) {
            self::throwInvalidLevelException($level);
        }

        return static::$levels[$level];
    }

    /**
     * Throw an InvalidArgumentException if the level is invalid.
     *
     * @param int $level The log level
     * @return void
     */
    protected static function throwInvalidLevelException($level): void
    {
        $message = sprintf('Level "%s" is not defined, use one of: %s', $level, implode(', ', array_keys(static::$levels)));

        throw new InvalidArgumentException($message);
    }

    /**
     * Converts PSR-3 levels to Monolog ones if necessary.
     *
     * @param string|int Level number (monolog) or name (PSR-3)
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
     * @return int
     */
    public static function toMonologLevel($level): int
    {
        if (is_string($level)) {
            if (defined(__CLASS__.'::'.strtoupper($level))) {
                return constant(__CLASS__.'::'.strtoupper($level));
            }

            self::throwInvalidLevelException($level);
        }

        return $level;
    }

    /**
     * Checks whether the Logger has a handler that listens on the given level.
     *
     * @param int $level The log level
     * @return bool
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
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed  $level   The log level
     * @param string $message The log message
     * @param array  $context The log context
     * @return void
     */
    public function log($level, $message, array $context = [])
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
     * @return void
     */
    public function debug($message, array $context = [])
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
     * @return void
     */
    public function info($message, array $context = [])
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
     * @return void
     */
    public function notice($message, array $context = [])
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
     * @return void
     */
    public function warning($message, array $context = [])
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
     * @return void
     */
    public function error($message, array $context = [])
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
     * @return void
     */
    public function critical($message, array $context = [])
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
     * @return void
     */
    public function alert($message, array $context = [])
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
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->addRecord(static::EMERGENCY, (string) $message, $context);
    }

    /**
     * Set the timezone to be used for the timestamp of log records.
     *
     * @param DateTimeZone $tz Timezone object
     * @return self
     */
    public function setTimezone(DateTimeZone $tz): self
    {
        $this->timezone = $tz;

        return $this;
    }

    /**
     * Set the timezone to be used for the timestamp of log records.
     *
     * @return DateTimeZone
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }
}
