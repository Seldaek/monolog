<?php

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Formatter\JsonFormatter;

/**
 * handle sending logs to the rabbitmq, using Amqp protocol;
 *
 * @author pomaxa none <pomaxa@gmail.com>
 */
class AmqpHandler extends AbstractProcessingHandler
{
    protected $exchange;
    protected $space;
    function __construct(\AMQPConnection $amqp, $exchange = 'log', $space = '', $level = Logger::DEBUG, $bubble = true)
    {
        $channel = new \AMQPChannel($amqp);
        $this->exchange = new \AMQPExchange($channel);
        $this->exchange->setName($exchange);
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

        $data = json_encode($record["formatted"]);

        $routingKey = substr(strtolower($record['level_name']),0,4 ).'.'.$this->space;

        $this->exchange->publish($data, $routingKey, 0,
            array('delivery_mode' => 2, 'Content-type' => 'application/json'));
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }
}