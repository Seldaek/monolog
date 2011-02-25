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
 * and uses them to store messages that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
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

    protected $name;

    /**
     * The handler stack
     *
     * @var array of Monolog\Handler\HandlerInterface
     */
    protected $handlers = array();

    protected $processors = array();

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function pushHandler(HandlerInterface $handler)
    {
        array_unshift($this->handlers, $handler);
    }

    public function popHandler()
    {
        if (!$this->handlers) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }
        return array_shift($this->handlers);
    }

    public function pushProcessor($callback)
    {
        array_unshift($this->processors, $callback);
    }

    public function popProcessor()
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }
        return array_shift($this->processors);
    }

    public function addMessage($level, $message)
    {
        if (!$this->handlers) {
            $this->pushHandler(new StreamHandler('php://stderr', self::DEBUG));
        }
        $message = array(
            'message' => $message,
            'level' => $level,
            'level_name' => self::getLevelName($level),
            'channel' => $this->name,
            'datetime' => new \DateTime(),
            'extra' => array(),
        );
        $handlerKey = null;
        foreach ($this->handlers as $key => $handler) {
            if ($handler->isHandling($message)) {
                $handlerKey = $key;
                break;
            }
        }
        if (null === $handlerKey) {
            return false;
        }
        foreach ($this->processors as $processor) {
            $message = call_user_func($processor, $message, $this);
        }
        while (isset($this->handlers[$handlerKey]) &&
            false === $this->handlers[$handlerKey]->handle($message)) {
            $handlerKey++;
        }
        return true;
    }

    public function addDebug($message)
    {
        return $this->addMessage(self::DEBUG, $message);
    }

    public function addInfo($message)
    {
        return $this->addMessage(self::INFO, $message);
    }

    public function addWarning($message)
    {
        return $this->addMessage(self::WARNING, $message);
    }

    public function addError($message)
    {
        return $this->addMessage(self::ERROR, $message);
    }

    public static function getLevelName($level)
    {
        return self::$levels[$level];
    }

    // ZF Logger Compat

    public function debug($message)
    {
        return $this->addMessage(self::DEBUG, $message);
    }

    public function info($message)
    {
        return $this->addMessage(self::INFO, $message);
    }

    public function notice($message)
    {
        return $this->addMessage(self::INFO, $message);
    }

    public function warn($message)
    {
        return $this->addMessage(self::WARNING, $message);
    }

    public function err($message)
    {
        return $this->addMessage(self::ERROR, $message);
    }

    public function crit($message)
    {
        return $this->addMessage(self::ERROR, $message);
    }

    public function alert($message)
    {
        return $this->addMessage(self::ERROR, $message);
    }

    public function emerg($message)
    {
        return $this->addMessage(self::ERROR, $message);
    }
}
