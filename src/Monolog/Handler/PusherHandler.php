<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Ross Crawford-d'Heureuse <sendrossemail@gmail.com>
 *
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;
use Pusher;


class PusherHandler extends AbstractProcessingHandler
{
    /**
     * @var internal variables
     */
    protected
        $pusher_key, 
        $pusher_secret, 
        $pusher_app_id, 
        $channel, 
        $event;

    private 
        $pusher;

    /**
     * @param string        $pusher_key
     * @param string        $pusher_secret
     * @param string        $pusher_app_id
     * @param string        $channel
     * @param string        $channel
     * @param int           $level
     * @param bool          $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($pusher_key, $pusher_secret, $pusher_app_id, $channel, $event='info_log', $level = Logger::INFO, $bubble = true)
    {
        $this->channel = $channel;
        $this->event = $event;
        //$this->pusher = new Pusher($pusher_key, $pusher_secret, $pusher_app_id, true);// DEBUG
        $this->pusher = new Pusher($pusher_key, $pusher_secret, $pusher_app_id);

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $this->pusher->trigger($this->channel, $this->event, $record);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}
