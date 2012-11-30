<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Ross Crawford-d'Heureuse <sendrossemail+monolog@gmail.com>
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
    protected $pusherKey;
    protected $pusherSecret;
    protected $pusherApp_id; 
    protected $channel;
    protected $event;
    protected $pusher;

    /**
     * @param string        $pusherKey
     * @param string        $pusherSecret
     * @param string        $pusherAppId
     * @param string        $channel
     * @param string        $event
     * @param int           $level
     * @param bool          $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($pusherKey, $pusherSecret, $pusherAppId, $channel, $event='info_log', $level = Logger::INFO, $bubble = true)
    {
        $this->channel = $channel;
        $this->event = $event;

        $this->pusher = new Pusher($pusherKey, $pusherSecret, $pusherAppId);

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
