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
use ZMQ;
use ZMQContext;
use ZMQSocket;

/**
 * Logs into a zmq socket
 *
 * Usage example:
 *
 * $log = new Logger('monolog');
 * $log->pushHandler(new ZmqHandler('tcp://192.168.56.101:5555'));
 *
 * @author Marius Krämer <marius.kraemer@mercoline.de>
 */
class ZmqHandler extends AbstractProcessingHandler
{
    /**
     * @var ZMQSocket $socket
     */
    protected $socket;

    /**
     * @var string $dsn
     */
    protected $dsn;

    /*
     * @var mixed $type ZMQ::SOCKET_ constant
     */
    protected $type;

    /*
     * @var mixed $persistId
     */
    protected $persistId;

    /**
     * @param string    $dsn       DSN to the endpoint
     * @param mixed     $type      ZMQ::SOCKET_ constant
     * @param mixed     $persistId String for persistent connections, null to disable persistent connections
     * @param ZMQSocket $socket    Ready-made socket
     * @param int       $level     The minimum logging level at which this handler will be triggered
     * @param bool      $bubble    Whether the messages that are handled can bubble up the stack or not
     *
     * @throws InvalidArgumentException If neither a dsn string nor a zeromq socket were passed
     */
    public function __construct($dsn = null, $type = ZMQ::SOCKET_PUSH, $persistId = 'monolog', $socket = null, $level = Logger::DEBUG, $bubble = true)
    {
        if (null === $socket && null === $dsn) {
            throw new \InvalidArgumentException('Either a dsn string or a zeromq socket have to be passed');
        }

        $this->socket = $socket;
        $this->dsn = $dsn;
        $this->type = $type;
        $this->persistId = $persistId;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        if (null === $this->socket) {
            $context = new ZMQContext();
            $this->socket = $context->getSocket($this->type, $this->persistId);
            $this->socket->connect($this->dsn);
        }

        $this->socket->send($record['formatted'], ZMQ::MODE_NOBLOCK);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}

