<?php

namespace Monolog\Handler;

use RollbarNotifier;

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
     * @param string   $token       post_server_item access token for the Rollbar project
     * @param string   $environment This can be set to any string
     * @param string   $root        Directory your code is in; used for linking stack traces
     * @param integer  $level       The minimum logging level at which this handler will be triggered
     * @param boolean  $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($token, $environment = 'production', $root = null, $level = Logger::ERROR, $bubble = true)
    {
        $this->rollbarNotifier = new RollbarNotifier(array(
            'access_token' => $token,
            'environment'  => $environment,
            'root'         => $root,
        ));

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if (isset($record['context']) and isset($record['context']['exception'])) {
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
