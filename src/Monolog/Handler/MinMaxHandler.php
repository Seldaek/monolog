<?php

namespace Monolog\Handler;

use Monolog\Logger;

/**
 * Simple handler wrapper that processes only log entries, which are between the min and max log level.
 *
 * @author Hennadiy Verkh
 */
class MinMaxHandler extends AbstractHandler
{
    /**
     * Handler or factory callable($record, $fingersCrossedHandler)
     *
     * @var callable|\Monolog\Handler\HandlerInterface
     */
    protected $handler;
    /**
     * Minimum level for logs that are passes to handler
     *
     * @var int
     */
    protected $minLevel;
    /**
     * Maximum level for logs that are passes to handler
     *
     * @var int
     */
    protected $maxLevel;
    /**
     * Whether the messages that are handled can bubble up the stack or not
     *
     * @var Boolean
     */
    protected $bubble;

    /**
     * @param callable|HandlerInterface $handler Handler or factory callable($record, $fingersCrossedHandler).
     * @param int                       $minLevel
     * @param int                       $maxLevel
     * @param Boolean                   $bubble Whether the messages that are handled can bubble up the stack or not
     *
     * @internal param \TtLibrary\Log\Handler\Maximum $int log level
     */
    public function __construct($handler, $minLevel = Logger::DEBUG, $maxLevel = Logger::EMERGENCY, $bubble = true)
    {

        $this->handler  = $handler;
        $this->minLevel = $minLevel;
        $this->maxLevel = $maxLevel;
        $this->bubble   = $bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        return $record['level'] >= $this->minLevel && $record['level'] <= $this->maxLevel;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        $this->handler->handle($record);

        return false === $this->bubble;
    }
}
