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

class FingersCrossedHandler extends AbstractHandler
{
    protected $handler;
    protected $actionLevel;
    protected $bufferSize;
    protected $enabled = false;
    protected $buffer = array();

    /**
     * @param callback|HandlerInterface $handler Handler or factory callback($message, $fingersCrossedHandler).
     * @param int $actionLevel the level at which this handler is triggered.
     * @param int $bufferSize how many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param Boolean $bubble
     */
    public function __construct($handler, $actionLevel = Logger::WARNING, $bufferSize = 0, $bubble = false)
    {
        $this->handler = $handler;
        $this->actionLevel = $actionLevel;
        $this->bufferSize = $bufferSize;
        $this->bubble = $bubble;
    }

    public function handle($message)
    {
        if (!$this->enabled) {
            $this->buffer[] = $message;
            if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
                array_shift($this->buffer);
            }
            if ($message['level'] >= $this->actionLevel) {
                $this->enabled = true;
                if (!$this->handler instanceof AbstractHandler) {
                    $this->handler = $this->handler($message, $this);
                }
                foreach ($this->buffer as $message) {
                    $this->handler->handle($message);
                }
                $this->buffer = array();
            }
        } else {
            $this->handler->handle($message);
        }
        return false === $this->bubble;
    }

    public function reset()
    {
        $this->enabled = false;
    }

    public function write($message)
    {
        throw new \LogicException('This method should not be called directly on the FingersCrossedHandler.');
    }
}