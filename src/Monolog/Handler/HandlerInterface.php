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
 * Interface that all Monolog Handlers must implement
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface HandlerInterface
{
    /**
     * Checks whether the handler handles the record.
     *
     * @return Boolean
     */
    function isHandling(array $record);

    /**
     * Handles a record.
     *
     * @param array $record The record to handle
     * @return Boolean Whether the handler stops the propagation in the stack or not.
     */
    function handle(array $record);

    /**
     * Handles a set of records.
     *
     * @param array $records The records to handle (an array of record arrays)
     * @return Boolean Whether the handler stops the propagation in the stack or not.
     */
    function handleBatch(array $records);

    /**
     * Adds a processor in the stack.
     *
     * @param callable $callback
     */
    function pushProcessor($callback);

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @return callable
     */
    function popProcessor();

    /**
     * Sets the formatter.
     *
     * @param FormatterInterface $formatter
     */
    function setFormatter(FormatterInterface $formatter);

    /**
     * Gets the formatter.
     *
     * @return FormatterInterface
     */
    function getFormatter();
}
