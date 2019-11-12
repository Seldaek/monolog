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

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossed\ActivationStrategyInterface;
use Monolog\Logger;
use Monolog\ResettableInterface;
use Monolog\Formatter\FormatterInterface;

/**
 * Buffers all records until a certain level is reached
 *
 * The advantage of this approach is that you don't get any clutter in your log files.
 * Only requests which actually trigger an error (or whatever your actionLevel is) will be
 * in the logs, but they will contain all records, not only those above the level threshold.
 *
 * You can then have a passthruLevel as well which means that at the end of the request,
 * even if it did not get activated, it will still send through log records of e.g. at least a
 * warning level.
 *
 * You can find the various activation strategies in the
 * Monolog\Handler\FingersCrossed\ namespace.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class FingersCrossedHandler extends Handler implements ProcessableHandlerInterface, ResettableInterface, FormattableHandlerInterface
{
    use ProcessableHandlerTrait;

    protected $handler;
    protected $activationStrategy;
    protected $buffering = true;
    protected $bufferSize;
    protected $buffer = [];
    protected $stopBuffering;
    protected $passthruLevel;
    protected $bubble;

    /**
     * @param callable|HandlerInterface              $handler            Handler or factory callable($record|null, $fingersCrossedHandler).
     * @param int|string|ActivationStrategyInterface $activationStrategy Strategy which determines when this handler takes action, or a level name/value at which the handler is activated
     * @param int                                    $bufferSize         How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param bool                                   $bubble             Whether the messages that are handled can bubble up the stack or not
     * @param bool                                   $stopBuffering      Whether the handler should stop buffering after being triggered (default true)
     * @param int|string                             $passthruLevel      Minimum level to always flush to handler on close, even if strategy not triggered
     */
    public function __construct($handler, $activationStrategy = null, int $bufferSize = 0, bool $bubble = true, bool $stopBuffering = true, $passthruLevel = null)
    {
        if (null === $activationStrategy) {
            $activationStrategy = new ErrorLevelActivationStrategy(Logger::WARNING);
        }

        // convert simple int activationStrategy to an object
        if (!$activationStrategy instanceof ActivationStrategyInterface) {
            $activationStrategy = new ErrorLevelActivationStrategy($activationStrategy);
        }

        $this->handler = $handler;
        $this->activationStrategy = $activationStrategy;
        $this->bufferSize = $bufferSize;
        $this->bubble = $bubble;
        $this->stopBuffering = $stopBuffering;

        if ($passthruLevel !== null) {
            $this->passthruLevel = Logger::toMonologLevel($passthruLevel);
        }

        if (!$this->handler instanceof HandlerInterface && !is_callable($this->handler)) {
            throw new \RuntimeException("The given handler (".json_encode($this->handler).") is not a callable nor a Monolog\Handler\HandlerInterface object");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record): bool
    {
        return true;
    }

    /**
     * Manually activate this logger regardless of the activation strategy
     */
    public function activate(): void
    {
        if ($this->stopBuffering) {
            $this->buffering = false;
        }

        $this->getHandler(end($this->buffer) ?: null)->handleBatch($this->buffer);
        $this->buffer = [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record): bool
    {
        if ($this->processors) {
            $record = $this->processRecord($record);
        }

        if ($this->buffering) {
            $this->buffer[] = $record;
            if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
                array_shift($this->buffer);
            }
            if ($this->activationStrategy->isHandlerActivated($record)) {
                $this->activate();
            }
        } else {
            $this->getHandler($record)->handle($record);
        }

        return false === $this->bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->flushBuffer();

        $this->handler->close();
    }

    public function reset()
    {
        $this->flushBuffer();

        $this->resetProcessors();

        if ($this->getHandler() instanceof ResettableInterface) {
            $this->getHandler()->reset();
        }
    }

    /**
     * Clears the buffer without flushing any messages down to the wrapped handler.
     *
     * It also resets the handler to its initial buffering state.
     */
    public function clear(): void
    {
        $this->buffer = [];
        $this->reset();
    }

    /**
     * Resets the state of the handler. Stops forwarding records to the wrapped handler.
     */
    private function flushBuffer(): void
    {
        if (null !== $this->passthruLevel) {
            $level = $this->passthruLevel;
            $this->buffer = array_filter($this->buffer, function ($record) use ($level) {
                return $record['level'] >= $level;
            });
            if (count($this->buffer) > 0) {
                $this->getHandler(end($this->buffer) ?: null)->handleBatch($this->buffer);
            }
        }

        $this->buffer = [];
        $this->buffering = true;
    }

    /**
     * Return the nested handler
     *
     * If the handler was provided as a factory callable, this will trigger the handler's instantiation.
     *
     * @return HandlerInterface
     */
    public function getHandler(array $record = null)
    {
        if (!$this->handler instanceof HandlerInterface) {
            $this->handler = call_user_func($this->handler, $record, $this);
            if (!$this->handler instanceof HandlerInterface) {
                throw new \RuntimeException("The factory callable should return a HandlerInterface");
            }
        }

        return $this->handler;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        $this->getHandler()->setFormatter($formatter);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->getHandler()->getFormatter();
    }
}
