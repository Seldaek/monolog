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
use \Pusher;


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
     * @param \Pusher       $pusher     Instance of the php-pusher lib
     * @param string        $channel
     * @param string        $event
     * @param int           $level
     * @param bool          $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(\Pusher $pusher, $level = Logger::INFO, $bubble = true)
    {
        $this->pusher = $pusher;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->pusher = null;
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $channel = (isset($record['context']['channel'])) ? $record['context']['channel']: throw new \InvalidArgumentException('"channel" must be defined in $record["context"]');
        $channel = (isset($record['context']['event'])) ? $record['context']['event']: throw new \InvalidArgumentException('"event" must be defined in $record["context"]');

        $this->pusher->trigger($channel, $event, $record);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}
