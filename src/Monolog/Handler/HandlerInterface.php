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
     * Checks whether the given record will be handled by this handler.
     *
     * This is mostly done for performance reasons, to avoid calling processors for nothing.
     *
     * @return Boolean
     */
    public function isHandling(array $record);

    /**
     * Handles a record.
     *
     * The return value of this function controls the bubbling process of the handler stack.
     *
     * @param  array   $record The record to handle
     * @return Boolean True means that this handler handled the record, and that bubbling is not permitted.
     *                 False means the record was either not processed or that this handler allows bubbling.
     */
    public function handle(array $record);

    /**
     * Handles a set of records at once.
     *
     * @param array $records The records to handle (an array of record arrays)
     */
    public function handleBatch(array $records);

    /**
     * Adds a processor in the stack.
     *
     * @param callable $callback
     */
    public function pushProcessor($callback);

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @return callable
     */
    public function popProcessor();

    /**
     * Sets the formatter.
     *
     * @param FormatterInterface $formatter
     */
    public function setFormatter(FormatterInterface $formatter);

    /**
     * Gets the formatter.
     *
     * @return FormatterInterface
     */
    public function getFormatter();
}
