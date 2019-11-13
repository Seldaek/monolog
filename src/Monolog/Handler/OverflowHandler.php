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
use Monolog\Formatter\FormatterInterface;

/**
 * Handler to only pass log messages when a certain threshold of number of messages is reached.
 *
 * This can be useful in cases of processing a batch of data, but you're for example only interested
 * in case it fails catastrophically instead of a warning for 1 or 2 events. Worse things can happen, right?
 *
 * Usage example:
 *
 * ```
 *   $log = new Logger('application');
 *   $handler = new SomeHandler(...)
 *
 *   // Pass all warnings to the handler when more than 10 & all error messages when more then 5
 *   $overflow = new OverflowHandler($handler, [Logger::WARNING => 10, Logger::ERROR => 5]);
 *
 *   $log->pushHandler($overflow);
 *```
 *
 * @author Kris Buist <krisbuist@gmail.com>
 */
class OverflowHandler extends AbstractHandler implements FormattableHandlerInterface
{
    /** @var HandlerInterface */
    private $handler;

    /** @var int[] */
    private $thresholdMap = [
        Logger::DEBUG => 0,
        Logger::INFO => 0,
        Logger::NOTICE => 0,
        Logger::WARNING => 0,
        Logger::ERROR => 0,
        Logger::CRITICAL => 0,
        Logger::ALERT => 0,
        Logger::EMERGENCY => 0,
    ];

    /**
     * Buffer of all messages passed to the handler before the threshold was reached
     *
     * @var mixed[][]
     */
    private $buffer = [];

    /**
     * @param HandlerInterface $handler
     * @param int[]            $thresholdMap Dictionary of logger level => threshold
     * @param int              $level
     * @param bool             $bubble
     */
    public function __construct(
        HandlerInterface $handler,
        array $thresholdMap = [],
        int $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        $this->handler = $handler;
        foreach ($thresholdMap as $thresholdLevel => $threshold) {
            $this->thresholdMap[$thresholdLevel] = $threshold;
        }
        parent::__construct($level, $bubble);
    }

    /**
     * Handles a record.
     *
     * All records may be passed to this method, and the handler should discard
     * those that it does not want to handle.
     *
     * The return value of this function controls the bubbling process of the handler stack.
     * Unless the bubbling is interrupted (by returning true), the Logger class will keep on
     * calling further handlers in the stack with a given log record.
     *
     * @param array $record The record to handle
     *
     * @return Boolean true means that this handler handled the record, and that bubbling is not permitted.
     *                 false means the record was either not processed or that this handler allows bubbling.
     */
    public function handle(array $record): bool
    {
        if ($record['level'] < $this->level) {
            return false;
        }

        $level = $record['level'];

        if (!isset($this->thresholdMap[$level])) {
            $this->thresholdMap[$level] = 0;
        }

        if ($this->thresholdMap[$level] > 0) {
            // The overflow threshold is not yet reached, so we're buffering the record and lowering the threshold by 1
            $this->thresholdMap[$level]--;
            $this->buffer[$level][] = $record;

            return false === $this->bubble;
        }

        if ($this->thresholdMap[$level] == 0) {
            // This current message is breaking the threshold. Flush the buffer and continue handling the current record
            foreach ($this->buffer[$level] ?? [] as $buffered) {
                $this->handler->handle($buffered);
            }
            $this->thresholdMap[$level]--;
            unset($this->buffer[$level]);
        }

        $this->handler->handle($record);

        return false === $this->bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        $this->handler->setFormatter($formatter);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->handler->getFormatter();
    }
}
