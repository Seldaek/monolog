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
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

/**
 * Handler to send messages to AMQP using php-amqplib
 *
 * @author Giorgio Premi <giosh94mhz@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class PhpAmqpLibHandler extends AbstractProcessingHandler
{
    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel $channel
     */
    protected $channel;

    /**
     * @var string $exchangeName
     */
    protected $exchangeName;

    /**
     * @param AMQPChannel $channel      AMQP channel, ready for use
     * @param string      $exchangeName
     * @param int         $level
     * @param bool        $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(AMQPChannel $channel, $exchangeName = 'log', $level = Logger::DEBUG, $bubble = true)
    {
        $this->channel = $channel;
        $this->exchangeName = $exchangeName;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $data = $record["formatted"];

        $routingKey = sprintf(
            '%s.%s',
            substr($record['level_name'], 0, 4),
            $record['channel']
        );

        $this->channel->basic_publish(
            new AMQPMessage(
                (string) $data,
                array(
                    'delivery_mode' => 2,
                    'content_type' => 'application/json'
                )
            ),
            $this->exchangeName,
            strtolower($routingKey)
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
    }
}
