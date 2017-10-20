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

use Aws\Sqs\SqsClient;
use Monolog\Logger;

/**
 * Writes to any sqs queue.
 *
 * @author Martijn van Calker <git@amvc.nl>
 */
class SqsHandler extends AbstractProcessingHandler
{
    /** @var SqsClient */
    private $client;
    /** @var string */
    private $queueUrl;

    public function __construct(SqsClient $sqsClient, $queueUrl, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->client = $sqsClient;
        $this->queueUrl = $queueUrl;
    }

    /**
     * Writes the record down to the log of the implementing handler.
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        if (!isset($record['formatted']) || 'string' !== gettype($record['formatted'])) {
            throw new \InvalidArgumentException('SqsHandler accepts only formatted records as a string');
        }

        $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => $record['formatted'],
        ]);
    }
}
