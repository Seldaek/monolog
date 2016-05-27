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
     * Handlers should still check the record levels within handle(), returning false in isHandling()
     * is no guarantee that handle() will not be called, and isHandling() might not be called
     * for a given record.
     *
     * @param array $record Partial log record containing only a level key
     *
     * @return Boolean
     */
    public function isHandling(array $record): bool;

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
     * @param  array   $record The record to handle
     * @return Boolean true means that this handler handled the record, and that bubbling is not permitted.
     *                        false means the record was either not processed or that this handler allows bubbling.
     */
    public function handle(array $record): bool;

    /**
     * Handles a set of records at once.
     *
     * @param array $records The records to handle (an array of record arrays)
     */
    public function handleBatch(array $records);

    /**
     * Closes the handler.
     *
     * This will be called automatically when the object is destroyed if you extend Monolog\Handler\Handler
     *
     * Implementations have to be idempotent (i.e. it should be possible to call close several times without breakage)
     * and ideally handlers should be able to reopen themselves on handle() after they have been closed.
     */
    public function close();
}
