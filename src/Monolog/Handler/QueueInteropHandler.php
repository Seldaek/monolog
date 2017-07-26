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

use Interop\Queue\PsrContext;
use Interop\Queue\PsrDestination;
use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;

class QueueInteropHandler extends AbstractProcessingHandler
{
    /**
     * @var PsrContext
     */
    private $context;

    /**
     * @var PsrDestination
     */
    private $destination;

    /**
     * @param PsrContext $context
     * @param PsrDestination|string    $destination
     * @param int                      $level
     * @param bool                     $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(PsrContext $context, $destination = 'log', $level = Logger::DEBUG, $bubble = true)
    {
        $this->context = $context;

        if (false == $destination instanceof PsrDestination) {
            $destination = $this->context->createTopic($destination);
        }

        $this->destination = $destination;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $message = $this->context->createMessage($record["formatted"], [
            'content_type' => 'application/json',
        ]);

        $this->context->createProducer()->send($this->destination, $message);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, false);
    }
}
