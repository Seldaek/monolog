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
    protected $bufferSize = 0;
    protected $bufferLimit;
    protected $flushOnOverflow;
    protected $buffer = array();

    /**
     * @param HandlerInterface $handler         Handler.
     * @param integer          $bufferLimit     How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param integer          $level           The minimum logging level at which this handler will be triggered
     * @param Boolean          $bubble          Whether the messages that are handled can bubble up the stack or not
     * @param Boolean          $flushOnOverflow If true, the buffer is flushed when the max size has been reached, by default oldest entries are discarded
     */
    public function __construct(HandlerInterface $handler, $bufferLimit = 0, $level = Logger::DEBUG, $bubble = true, $flushOnOverflow = false)
    {
        parent::__construct($level, $bubble);
        $this->handler = $handler;
        $this->bufferLimit = (int) $bufferLimit;
        $this->flushOnOverflow = $flushOnOverflow;

        // __destructor() doesn't get called on Fatal errors
        register_shutdown_function(array($this, 'close'));
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if ($record['level'] < $this->level) {
            return false;
        }

        if ($this->bufferLimit > 0 && $this->bufferSize === $this->bufferLimit) {
            if ($this->flushOnOverflow) {
                $this->flush();
            } else {
                array_shift($this->buffer);
                $this->bufferSize--;
            }
        }

        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        $this->buffer[] = $record;
        $this->bufferSize++;

        return false === $this->bubble;
    }

    public function flush()
    {
        if ($this->bufferSize === 0) {
            return;
        }

        $this->handler->handleBatch($this->buffer);
        $this->bufferSize = 0;
        $this->buffer = array();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->flush();
    }
}
