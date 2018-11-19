<?php declare(strict_types=1);

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
use Monolog\ResettableInterface;

/**
 * Buffers all records until closing the handler and then pass them as batch.
 *
 * This is useful for a MailHandler to send only one mail per request instead of
 * sending one per log message.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class BufferHandler extends AbstractHandler implements ProcessableHandlerInterface
{
    use ProcessableHandlerTrait;

    protected $handler;
    protected $bufferSize = 0;
    protected $bufferLimit;
    protected $flushOnOverflow;
    protected $buffer = [];
    protected $initialized = false;

    /**
     * @param HandlerInterface $handler         Handler.
     * @param int              $bufferLimit     How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param string|int       $level           The minimum logging level at which this handler will be triggered
     * @param bool             $bubble          Whether the messages that are handled can bubble up the stack or not
     * @param bool             $flushOnOverflow If true, the buffer is flushed when the max size has been reached, by default oldest entries are discarded
     */
    public function __construct(HandlerInterface $handler, int $bufferLimit = 0, $level = Logger::DEBUG, bool $bubble = true, bool $flushOnOverflow = false)
    {
        parent::__construct($level, $bubble);
        $this->handler = $handler;
        $this->bufferLimit = (int) $bufferLimit;
        $this->flushOnOverflow = $flushOnOverflow;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record): bool
    {
        if ($record['level'] < $this->level) {
            return false;
        }

        if (!$this->initialized) {
            // __destructor() doesn't get called on Fatal errors
            register_shutdown_function([$this, 'close']);
            $this->initialized = true;
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
            $record = $this->processRecord($record);
        }

        $this->buffer[] = $record;
        $this->bufferSize++;

        return false === $this->bubble;
    }

    public function flush(): void
    {
        if ($this->bufferSize === 0) {
            return;
        }

        $this->handler->handleBatch($this->buffer);
        $this->clear();
    }

    public function __destruct()
    {
        // suppress the parent behavior since we already have register_shutdown_function()
        // to call close(), and the reference contained there will prevent this from being
        // GC'd until the end of the request
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->flush();

        $this->handler->close();
    }

    /**
     * Clears the buffer without flushing any messages down to the wrapped handler.
     */
    public function clear(): void
    {
        $this->bufferSize = 0;
        $this->buffer = [];
    }

    public function reset()
    {
        $this->flush();

        parent::reset();

        $this->resetProcessors();

        if ($this->handler instanceof ResettableInterface) {
            $this->handler->reset();
        }
    }
}
