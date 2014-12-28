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

use Monolog\Formatter\FormatterInterface;

/**
 * Sampling handler
 *
 * A sampled event stream can be useful for logging high frequency events in
 * a production environment where you only need an idea of what is happening
 * and are not concerned with capturing every occurrence. Since the decision to
 * handle or not handle a particular event is determined randomly, the
 * resulting sampled log is not guaranteed to contain 1/N of the events that
 * occurred in the application, but based on the Law of large numbers, it will
 * tend to be close to this ratio with a large number of attempts.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @author Kunal Mehta <legoktm@gmail.com>
 */
class SamplingHandler extends AbstractHandler
{
    /**
     * @var HandlerInterface $delegate
     */
    protected $delegate;

    /**
     * @var int $factor
     */
    protected $factor;

    /**
     * @param HandlerInterface $handler Wrapped handler
     * @param int $factor Sample factor
     */
    public function __construct(HandlerInterface $handler, $factor)
    {
        parent::__construct();
        $this->delegate = $handler;
        $this->factor = $factor;
    }

    public function isHandling(array $record)
    {
        return $this->delegate->isHandling($record);
    }

    public function handle(array $record)
    {
        if ($this->isHandling($record)
            && mt_rand(1, $this->factor) === 1)
        {
            return $this->delegate->handle($record);
        }
        return false;
    }

    public function pushProcessor($callback)
    {
        $this->delegate->pushProcessor($callback);
        return $this;
    }

    public function popProcessor()
    {
        return $this->delegate->popProcessor();
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        $this->delegate->setFormatter($formatter);
        return $this;
    }

    public function getFormatter()
    {
        return $this->delegate->getFormatter();
    }
}
