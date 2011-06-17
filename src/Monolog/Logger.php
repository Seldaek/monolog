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

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;

/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Logger
{
    /**
     * Debug messages
     */
    const DEBUG = 100;

    /**
     * Messages you usually don't want to see
     */
    const INFO = 200;

    /**
     * Exceptional occurences that are not errors
     *
     * This is typically the logging level you want to use
     */
    const WARNING = 300;

    /**
     * Errors
     */
    const ERROR = 400;

    /**
     * Critical conditions (component unavailable, etc.)
     */
    const CRITICAL = 500;

    /**
     * Action must be taken immediately (entire service down)
     *
     * Should trigger alert by sms, email, etc.
     */
    const ALERT = 550;

    protected static $levels = array(
        100 => 'DEBUG',
        200 => 'INFO',
        300 => 'WARNING',
        400 => 'ERROR',
        500 => 'CRITICAL',
        550 => 'ALERT',
    );

    protected $name;

    /**
     * The handler stack
     *
     * @var array of Monolog\Handler\HandlerInterface
     */
    protected $handlers = array();

    protected $processors = array();

    /**
     * @param string $name The logging channel
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Pushes an handler on the stack.
     *
     * @param HandlerInterface $handler
     */
    public function pushHandler(HandlerInterface $handler)
    {
        array_unshift($this->handlers, $handler);
    }

    /**
     * Pops an handler from the stack
     *
     * @return HandlerInterface
     */
    public function popHandler()
    {
        if (!$this->handlers) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }
        return array_shift($this->handlers);
    }

    /**
     * Adds a processor in the stack.
     *
     * @param callable $callback
     */
    public function pushProcessor($callback)
    {
        array_unshift($this->processors, $callback);
    }

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @return callable
     */
    public function popProcessor()
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }
        return array_shift($this->processors);
    }

    /**
     * Adds a log record.
     *
     * @param integer $level The logging level
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = array())
    {
        if (!$this->handlers) {
            $this->pushHandler(new StreamHandler('php://stderr', self::DEBUG));
        }
        $record = array(
            'message' => (string) $message,
            'context' => $context,
            'level' => $level,
            'level_name' => self::getLevelName($level),
            'channel' => $this->name,
            'datetime' => new \DateTime(),
            'extra' => array(),
        );
        // check if any message will handle this message
        $handlerKey = null;
        foreach ($this->handlers as $key => $handler) {
            if ($handler->isHandling($record)) {
                $handlerKey = $key;
                break;
            }
        }
        // none found
        if (null === $handlerKey) {
            return false;
        }
        // found at least one, process message and dispatch it
        foreach ($this->processors as $processor) {
            $record = call_user_func($processor, $record);
        }
        while (isset($this->handlers[$handlerKey]) &&
            false === $this->handlers[$handlerKey]->handle($record)) {
            $handlerKey++;
        }

        return true;
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addDebug($message, array $context = array())
    {
        return $this->addRecord(self::DEBUG, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addInfo($message, array $context = array())
    {
        return $this->addRecord(self::INFO, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addWarning($message, array $context = array())
    {
        return $this->addRecord(self::WARNING, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addError($message, array $context = array())
    {
        return $this->addRecord(self::ERROR, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addCritical($message, array $context = array())
    {
        return $this->addRecord(self::CRITICAL, $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addAlert($message, array $context = array())
    {
        return $this->addRecord(self::ALERT, $message, $context);
    }

    /**
     * Gets the name of the logging level.
     *
     * @param integer $level
     * @return string
     */
    public static function getLevelName($level)
    {
        return self::$levels[$level];
    }

    // ZF Logger Compat

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function debug($message, array $context = array())
    {
        return $this->addRecord(self::DEBUG, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function info($message, array $context = array())
    {
        return $this->addRecord(self::INFO, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function notice($message, array $context = array())
    {
        return $this->addRecord(self::INFO, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function warn($message, array $context = array())
    {
        return $this->addRecord(self::WARNING, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function err($message, array $context = array())
    {
        return $this->addRecord(self::ERROR, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function crit($message, array $context = array())
    {
        return $this->addRecord(self::CRITICAL, $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function alert($message, array $context = array())
    {
        return $this->addRecord(self::ALERT, $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows to have an easy ZF compatibility.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function emerg($message, array $context = array())
    {
        return $this->addRecord(self::ALERT, $message, $context);
    }
}
