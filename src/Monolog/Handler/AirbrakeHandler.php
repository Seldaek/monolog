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

use Airbrake\Client;
use Airbrake\Notice;
use Exception;
use Monolog\Logger;

/**
 * Sends errors to Airbrake
 */
class AirbrakeHandler extends AbstractProcessingHandler
{
    /**
     * Airbrake client
     *
     * @var Client
     */
    protected $airbrakeClient;

    /**
     * @param Client  $airbrakeClient The Airbrake client object which will be used to send the log messages
     * @param integer $level          The minimum logging level at which this handler will be triggered
     * @param boolean $bubble         Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Client $airbrakeClient, $level = Logger::ERROR, $bubble = true)
    {
        $this->airbrakeClient = $airbrakeClient;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof Exception) {
            $this->airbrakeClient->notifyOnException($record['context']['exception']);
        } else {
            $extraParameters = array(
                'level'    => $record['level'],
                'channel'  => $record['channel'],
                'datetime' => $record['datetime']->format('U'),
            );

            $notice = new Notice();
            $notice->load(array(
                'errorClass'      => $record['level_name'],
                'errorMessage'    => $record['message'],
                'backtrace'       => debug_backtrace(),
                'extraParameters' => array_merge($record['context'], $record['extra'], $extraParameters),
            ));

            $this->airbrakeClient->notify($notice);
        }
    }
}
