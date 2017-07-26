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

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;

class AmqpInteropHandler extends AbstractProcessingHandler
{
    /**
     * @var AmqpContext
     */
    private $context;

    /**
     * @var AmqpDestination
     */
    private $destination;

    /**
     * @var bool
     */
    private $shouldDeclare;

    /**
     * @var bool
     */
    private $wasDeclared;

    /**
     * @param AmqpContext            $context
     * @param AmqpDestination|string $destination
     * @param int                    $level
     * @param bool                   $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(AmqpContext $context, $destination = 'log', $level = Logger::DEBUG, $bubble = true)
    {
        $this->context = $context;
        $this->shouldDeclare = true;
        $this->wasDelcared = false;

        if (false == $destination instanceof AmqpDestination) {
            $destination = $this->context->createQueue($destination);
        }

        $this->destination = $destination;

        parent::__construct($level, $bubble);
    }

    /**
     * @param bool $shouldDeclare
     */
    public function setShouldDeclare(bool $shouldDeclare)
    {
        $this->shouldDeclare = $shouldDeclare;
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        if ($this->shouldDeclare && false == $this->wasDeclared) {
            if ($this->destination instanceof AmqpTopic) {
                $this->context->declareTopic($this->destination);
            }
            if ($this->destination instanceof AmqpQueue) {
                $this->context->declareQueue($this->destination);
            }

            $this->wasDeclared = true;
        }

        $message = $this->context->createMessage($record["formatted"]);
        $message->setContentType('application/json');
        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
        $message->setRoutingKey($this->getRoutingKey($record));

        $this->context->createProducer()->send($this->destination, $message);
    }

    /**
     * Gets the routing key for the AMQP exchange
     *
     * @param  array  $record
     * @return string
     */
    private function getRoutingKey(array $record)
    {
        $routingKey = sprintf('%s.%s', $record['level_name'], $record['channel']);

        return strtolower($routingKey);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
    }
}
