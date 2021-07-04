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

use Monolog\ResettableInterface;
use Monolog\Processor\ProcessorInterface;

/**
 * Helper trait for implementing ProcessableInterface
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Record from \Monolog\Logger
 */
trait ProcessableHandlerTrait
{
    /**
     * @var callable[]
     * @phpstan-var array<ProcessorInterface|callable(Record): Record>
     */
    protected $processors = [];

    /**
     * {@inheritdoc}
     */
    public function pushProcessor(callable $callback): HandlerInterface
    {
        array_unshift($this->processors, $callback);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function popProcessor(): callable
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    /**
     * Processes a record.
     *
     * @phpstan-param  Record $record
     * @phpstan-return Record
     */
    protected function processRecord(array $record): array
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        return $record;
    }

    protected function resetProcessors(): void
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof ResettableInterface) {
                $processor->reset();
            }
        }
    }
}
