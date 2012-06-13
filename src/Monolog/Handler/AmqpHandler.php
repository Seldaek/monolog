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

class AmqpHandler extends AbstractProcessingHandler
{
    /** @var \AMQPExchange $exchange */
    protected $exchange;
    /** @var string $space */
    protected $space;

    /**
     * @param \AMQPConnection $amqp AMQP connection, ready for use
     * @param string $exchangeName
     * @param string $space string to be able better manage routing keys
     * @param int $level
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     */
    function __construct(\AMQPConnection $amqp, $exchangeName = 'log', $space = '', $level = Logger::DEBUG, $bubble = true)
    {
        $this->space = $space;
        $channel = new \AMQPChannel($amqp);
        $this->exchange = new \AMQPExchange($channel);
        $this->exchange->setName($exchangeName);
        parent::__construct($level, $bubble);
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     * @return void
     */
    protected function write(array $record)
    {
        $data = $record["formatted"];
        $routingKey = substr(strtolower($record['level_name']),0,4 ).'.'.$this->space;
        $this->exchange->publish($data, $routingKey, 0,
            array('delivery_mode' => 2, 'Content-type' => 'application/json'));
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}
