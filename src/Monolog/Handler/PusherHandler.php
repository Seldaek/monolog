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


class PusherHandler extends AbstractProcessingHandler
{
    /**
     * @var internal variables
     */
    protected $pusher;

    /**
     * @param \Pusher       $pusher     Instance of the php-pusher lib
     * @param int           $level
     * @param bool          $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(\Pusher $pusher, $level = Logger::INFO, $bubble = true)
    {
        $this->pusher = $pusher;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        if  (!isset($record['context']['channel'])) {
            throw new \InvalidArgumentException('"channel" must be defined in $record["context"]');
        }
        if  (!isset($record['context']['event'])) {
            throw new \InvalidArgumentException('"event" must be defined in $record["context"]');
        }

        $this->pusher->trigger($record['context']['channel'], $record['context']['event'], $record);
    }

}
