<?php

namespace Monolog\Handler;

use RollbarNotifier;
use Exception;

/**
 * Sends errors to Rollbar
 *
 * @author Paul Statezny <paulstatezny@gmail.com>
 */
class RollbarHandler extends AbstractProcessingHandler
{
    /**
     * Rollbar notifier
     *
     * @var RollbarNotifier
     */
    protected $rollbarNotifier;

    /**
     * @param RollbarNotifier  $rollbarNotifier RollbarNotifier object constructed with valid token
     * @param integer          $level           The minimum logging level at which this handler will be triggered
     * @param boolean          $bubble          Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(RollbarNotifier $rollbarNotifier, $level = Logger::ERROR, $bubble = true)
    {
        $this->rollbarNotifier = $rollbarNotifier;

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof Exception) {
            $this->rollbarNotifier->report_exception($record['context']['exception']);
        } else {
            $this->rollbarNotifier->report_message(
                $record['message'],
                $record['level_name'],
                $record['extra']
            );
        }
    }

    public function __destruct()
    {
        $this->rollbarNotifier->flush();
    }
}
