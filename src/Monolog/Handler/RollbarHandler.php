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

use RollbarNotifier;
use Exception;
use Monolog\Logger;

/**
 * Sends errors to Rollbar
 *
 * @author Paul Statezny <paulstatezny@gmail.com>
 */
class RollbarHandler extends AbstractProcessingHandler
{
    /**
     * Array of fatal types that need the queue to be flushed immediately
     * as the destructor will not be called.
     *
     * @var array
     */
    protected $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /**
     * Rollbar notifier
     *
     * @var RollbarNotifier
     */
    protected $rollbarNotifier;

    /**
     * @param RollbarNotifier $rollbarNotifier RollbarNotifier object constructed with valid token
     * @param integer         $level           The minimum logging level at which this handler will be triggered
     * @param boolean         $bubble          Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(RollbarNotifier $rollbarNotifier, $level = Logger::ERROR, $bubble = true)
    {
        $this->rollbarNotifier = $rollbarNotifier;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof Exception) {
            $this->rollbarNotifier->report_exception($record['context']['exception']);
        } else {
            $extraData = array(
                'level' => $record['level'],
                'channel' => $record['channel'],
                'datetime' => $record['datetime']->format('U'),
            );

            $this->rollbarNotifier->report_message(
                $record['message'],
                $record['level_name'],
                array_merge($record['context'], $record['extra'], $extraData)
            );
        }

        // If the record is of a 'fatal' nature, close the handler
        if (isset($record['context']['code']) && in_array($record['context']['code'], $this->fatalTypes)) {
            $this->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->rollbarNotifier->flush();
    }
}
