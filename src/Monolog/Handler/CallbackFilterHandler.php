<?php

namespace Monolog\Handler;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;

/**
 * Simple handler wrapper that filters records based on a list of callback functions.
 *
 * @author Christophe Coevoet
 * @author Laurent Laville <pear@laurent-laville.org>
 */
class CallbackFilterHandler extends AbstractHandler
{
    /**
     * Handler or factory callable($record, $this)
     *
     * @var callable|\Monolog\Handler\HandlerInterface
     */
    protected $handler;

    /**
     * Filters callable to restrict log records.
     *
     * @var callable
     */
    protected $filters;

    /**
     * Whether the messages that are handled can bubble up the stack or not
     *
     * @var boolean
     */
    protected $bubble;

    /**
     * @param callable|HandlerInterface $handler Handler or factory callable($record, $this).
     * @param callable[]                $filters A list of filters to apply
     * @param boolean                   $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($handler, array $filters, $bubble = true)
    {
        $this->handler = $handler;
        $this->bubble  = $bubble;
        $this->filters = array();

        if (!$this->handler instanceof HandlerInterface) {
            if (!is_callable($this->handler)) {
                throw new \RuntimeException(
                    "The given handler (" . json_encode($this->handler)
                    . ") is not a callable nor a Monolog\\Handler\\HandlerInterface object"
                );
            }
        }

        foreach ($filters as $filter) {
            if (!is_callable($filter)) {
                throw new \RuntimeException(
                    "The given filter (" . json_encode($filter)
                    . ") is not a callable object"
                );
            }
            $this->filters[] = $filter;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        foreach ($this->filters as $filter) {
            if (!call_user_func($filter, $record, $this->handler->getLevel())) {
                return false;
            }
        }

        return true;
    }

    // The following methods are copied from FilterHandler

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        // The same logic as in FingersCrossedHandler
        if (!$this->handler instanceof HandlerInterface) {
            $this->handler = call_user_func($this->handler, $record, $this);
            if (!$this->handler instanceof HandlerInterface) {
                throw new \RuntimeException("The factory callable should return a HandlerInterface");
            }
        }

        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        $this->handler->handle($record);

        return false === $this->bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        $filtered = array();
        foreach ($records as $record) {
            if ($this->isHandling($record)) {
                $filtered[] = $record;
            }
        }

        $this->handler->handleBatch($filtered);
    }
}
