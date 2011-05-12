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
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;

/**
 * Base Handler class providing the Handler structure
 *
 * Classes extending it should (in most cases) only implement write($record)
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
abstract class AbstractHandler implements HandlerInterface
{
    protected $level = Logger::DEBUG;
    protected $bubble = false;

    /**
     * @var FormatterInterface
     */
    protected $formatter;
    protected $processors = array();

    /**
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($level = Logger::DEBUG, $bubble = false)
    {
        $this->level = $level;
        $this->bubble = $bubble;
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        return $record['level'] >= $this->level;
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /**
     * Closes the handler.
     *
     * This will be called automatically when the object is destroyed
     */
    public function close()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function pushProcessor($callback)
    {
        array_unshift($this->processors, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function popProcessor()
    {
        return array_shift($this->processors);
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter()
    {
        if (!$this->formatter) {
            $this->formatter = $this->getDefaultFormatter();
        }

        return $this->formatter;
    }

    /**
     * Sets minimum logging level at which this handler will be triggered.
     *
     * @param integer $level
     */
    public function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * Gets minimum logging level at which this handler will be triggered.
     *
     * @return integer
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Sets the bubbling behavior.
     *
     * @param Boolean $bubble True means that bubbling is not permitted.
     *                        False means that this handler allows bubbling.
     */
    public function setBubble($bubble)
    {
        $this->bubble = $bubble;
    }

    /**
     * Gets the bubbling behavior.
     *
     * @return Boolean True means that bubbling is not permitted.
     *                 False means that this handler allows bubbling.
     */
    public function getBubble()
    {
        return $this->bubble;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Gets the default formatter.
     *
     * @return FormatterInterface
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter();
    }
}
