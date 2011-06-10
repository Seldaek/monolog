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

/**
 * Buffers all records until closing the handler and then pass them as batch.
 *
 * This is useful for a MailHandler to send only one mail per request instead of
 * sending one per log message.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class BufferHandler extends AbstractHandler
{
    protected $handler;
    protected $bufferSize;
    protected $buffer = array();

    /**
     * @param HandlerInterface $handler Handler.
     * @param integer $bufferSize How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(HandlerInterface $handler, $bufferSize = 0, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->handler = $handler;
        $this->bufferSize = $bufferSize;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if ($record['level'] < $this->level) {
            return false;
        }

        $this->buffer[] = $record;
        if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
            array_shift($this->buffer);
        }

        return false === $this->bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->handler->handleBatch($this->buffer);
    }
}