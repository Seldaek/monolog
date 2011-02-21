<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

abstract class AbstractHandler implements HandlerInterface
{
    protected $level;
    protected $bubble;
    protected $parent;

    protected $formatter;
    protected $processors = array();

    public function __construct($level = Logger::DEBUG, $bubble = false)
    {
        $this->level = $level;
        $this->bubble = $bubble;
    }

    public function getHandler($message)
    {
        if ($message['level'] < $this->level) {
            return $this->parent ? $this->parent->getHandler($message) : null;
        }
        return $this;
    }

    public function handle($message)
    {
        if ($message['level'] < $this->level) {
            return $this->parent ? $this->parent->handle($message) : false;
        }

        $originalMessage = $message;
        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $message = call_user_func($processor, $message, $this);
            }
        }

        if (!$this->formatter) {
            $this->formatter = $this->getDefaultFormatter();
        }
        $message = $this->formatter->format($message);

        $this->write($message);
        if ($this->bubble && $this->parent) {
            $this->parent->handle($originalMessage);
        }
        return true;
    }

    abstract public function write($message);

    public function close()
    {
    }

    public function pushProcessor($callback)
    {
        $this->processors[] = $callback;
    }

    public function popProcessor()
    {
        return array_pop($this->processors);
    }

    public function setFormatter($formatter)
    {
        $this->formatter = $formatter;
    }

    public function getFormatter()
    {
        return $this->formatter;
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setBubble($bubble)
    {
        $this->bubble = $bubble;
    }

    public function getBubble()
    {
        return $this->bubble;
    }

    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Sets the parent handler
     *
     * @param Monolog\Handler\HandlerInterface
     */
    public function setParent(HandlerInterface $parent)
    {
        $this->parent = $parent;
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function getDefaultFormatter()
    {
        return new LineFormatter();
    }
}
