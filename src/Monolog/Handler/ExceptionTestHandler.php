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

/**
 * Used for testing purposes.
 *
 * It records all records and gives you access to them for verification. It
 * throws an exception from handle and handleBatch to test the
 * WhatFailureGroupHandler Class.
 *
 * @author Craig D'Amelio <craig@damelio.ca>
 */
class ExceptionTestHandler extends TestHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle(array $record) {
        $return = parent::handle($record);
        throw new \Exception("ExceptionTestHandler::handle");
    }
}
