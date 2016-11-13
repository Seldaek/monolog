<?php declare(strict_types=1);

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
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use AMQPExchange;

class AmqpHandler extends AbstractProcessingHandler
{
    /**
     * @var AMQPExchange|AMQPChannel $exchange
     */
    protected $exchange;

    /**
     * @var string
     */
    protected $exchangeName;

    /**
     * @param AMQPExchange|AMQPChannel $exchange     AMQPExchange (php AMQP ext) or PHP AMQP lib channel, ready for use
     * @param string                   $exchangeName Optional exchange name, for AMQPChannel (PhpAmqpLib) only
     * @param int                      $level
     * @param bool                     $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($exchange, $exchangeName = null, $level = Logger::DEBUG, $bubble = true)
    {
        if ($exchange instanceof AMQPChannel) {
            $this->exchangeName = $exchangeName;
        } elseif (!$exchange instanceof AMQPExchange) {
            throw new \InvalidArgumentException('PhpAmqpLib\Channel\AMQPChannel or AMQPExchange instance required');
        } elseif ($exchangeName) {
            @trigger_error('The $exchangeName parameter can only be passed when using PhpAmqpLib, if using an AMQPExchange instance configure it beforehand', E_USER_DEPRECATED);
        }
        $this->exchange = $exchange;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $data = $record["formatted"];
        $routingKey = $this->getRoutingKey($record);

        if ($this->exchange instanceof AMQPExchange) {
            $this->exchange->publish(
                $data,
                $routingKey,
                0,
                [
                    'delivery_mode' => 2,
                    'content_type' => 'application/json',
                ]
            );
        } else {
            $this->exchange->basic_publish(
                $this->createAmqpMessage($data),
                $this->exchangeName,
                $routingKey
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records)
    {
        if ($this->exchange instanceof AMQPExchange) {
            parent::handleBatch($records);

            return;
        }

        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }

            $record = $this->processRecord($record);
            $data = $this->getFormatter()->format($record);

            $this->exchange->batch_basic_publish(
                $this->createAmqpMessage($data),
                $this->exchangeName,
                $this->getRoutingKey($record)
            );
        }

        $this->exchange->publish_batch();
    }

    /**
     * Gets the routing key for the AMQP exchange
     *
     * @param  array  $record
     * @return string
     */
    protected function getRoutingKey(array $record)
    {
        $routingKey = sprintf('%s.%s', $record['level_name'], $record['channel']);

        return strtolower($routingKey);
    }

    /**
     * @param  string      $data
     * @return AMQPMessage
     */
    private function createAmqpMessage($data)
    {
        return new AMQPMessage(
            (string) $data,
            [
                'delivery_mode' => 2,
                'content_type' => 'application/json',
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
    }
}
