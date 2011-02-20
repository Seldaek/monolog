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
    protected $processor;

    public function __construct($level = Logger::DEBUG, $bubble = false)
    {
        $this->level = $level;
        $this->bubble = $bubble;
    }

    public function handle($message)
    {
        if ($message['level'] < $this->level) {
            return false;
        }

        if ($this->processor) {
            $message = call_user_func($this->processor, $message, $this);
        }

        if (!$this->formatter) {
            $this->formatter = $this->getDefaultFormatter();
        }
        $message = $this->formatter->format($message);

        $this->write($message);
        return false === $this->bubble;
    }

    abstract public function write($message);

    public function close()
    {
    }

    public function setProcessor($callback)
    {
        $this->processor = $callback;
    }

    public function getProcessor()
    {
        return $this->processor;
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
