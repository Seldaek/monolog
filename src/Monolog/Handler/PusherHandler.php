<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Monolog\Handler;

use Monolog\Logger;

/**
 * Logs to Pusher.com
 *
 *  $log = new Logger('My_Pusher_Com_Channel_name');
 *  $log->pushHandler(new PusherHandler(new Pusher($key, $secret, $app_id)));
 *
 * set your pusher client to recieve from the channel "My_Pusher_Com_Channel_name"
 * set your pusherclient to bind on the "info_log" event
 *
 * @author Ross Crawford-d'Heureuse <sendrossemail+monolog@gmail.com>
 */
class PusherHandler extends AbstractProcessingHandler
{
    /**
     * @var internal variables
     */
    protected $pusher;
    public $pusher_event;

    /**
     * @param Pusher        $pusher          Instance of the php-pusher lib
     * @param string        $pusher_event    The name of the default pusher event, usually basedon the $level name
     * @param int           $level
     * @param bool          $bubble          Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($pusher, $pusher_event = 'info_log', $level = Logger::INFO, $bubble = true)
    {
        $this->pusher = $pusher;
        $this->pusher_event = $pusher_event;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $channel = $record['channel'];

        $this->pusher->trigger($channel, $this->pusher_event, $record);
    }

}
