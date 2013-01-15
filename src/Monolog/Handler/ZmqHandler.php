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
use Monolog\Formatter\JsonFormatter;

/**
 * Logs into a zmq socket
 *
 * Usage example:
 *
 * $log = new Logger('application');
 * $context = new \ZMQContext(1, true);
 * $socket = $context->getSocket(\ZMQ::SOCKET_PUSH, 'monolog');
 * $socket->connect('192.168.56.101:5555');
 * $zmqHandler = new ZmqHandler($socket);
 * $log->pushHandler($zmqHandler);
 *
 * @author Marius Krämer <marius.kraemer@mercoline.de>
 */
class ZmqHandler extends AbstractProcessingHandler
{
    /**
     * @var \ZMQSocket $socket
     */
    protected $socket;

    /**
     * @param \ZMQSocket $socket Connected zeromq socket
     * @param int        $level  The minimum logging level at which this handler will be triggered
     * @param bool       $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($socket, $level = Logger::DEBUG, $bubble = true)
    {
        $this->socket = $socket;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $data = $record["formatted"];

        $this->socket->send($data);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}

