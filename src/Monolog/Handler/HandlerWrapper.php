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

use Monolog\Formatter\FormatterInterface;

/**
 * This simple wrapper class can be used to extend handlers functionality.
 *
 * Example: A filtering handle. Inherit from this class, override isHandling() like this
 *
 * public function isHandling(array $record)
 * {
 *      if ($record meets certain conditions) {
 *          return false;
 *      }
 *      return $this->handler->isHandling($record);
 * }
 *
 * @author Alexey Karapetov <alexey@karapetov.com>
 */
class HandlerWrapper implements HandlerInterface, ProcessableHandlerInterface, FormattableHandlerInterface
{
    /**
     * @var HandlerInterface
     */
    protected $handler;

    /**
     * HandlerWrapper constructor.
     * @param HandlerInterface $handler
     */
    public function __construct(HandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record): bool
    {
        return $this->handler->isHandling($record);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record): bool
    {
        return $this->handler->handle($record);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        return $this->handler->handleBatch($records);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->handler->close();
    }

    /**
     * {@inheritdoc}
     */
    public function pushProcessor(callable $callback): HandlerInterface
    {
        if ($this->handler instanceof ProcessableHandlerInterface) {
            $this->handler->pushProcessor($callback);

            return $this;
        }

        throw new \LogicException('The wrapped handler does not implement ' . ProcessableHandlerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function popProcessor(): callable
    {
        if ($this->handler instanceof ProcessableHandlerInterface) {
            return $this->handler->popProcessor();
        }

        throw new \LogicException('The wrapped handler does not implement ' . ProcessableHandlerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        if ($this->handler instanceof FormattableHandlerInterface) {
            $this->handler->setFormatter($formatter);
        }

        throw new \LogicException('The wrapped handler does not implement ' . FormattableHandlerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): FormatterInterface
    {
        if ($this->handler instanceof FormattableHandlerInterface) {
            return $this->handler->getFormatter($formatter);
        }

        throw new \LogicException('The wrapped handler does not implement ' . FormattableHandlerInterface::class);
    }
}
