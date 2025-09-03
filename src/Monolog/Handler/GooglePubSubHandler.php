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

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Monolog\Level;
use Monolog\Utils;
use Monolog\LogRecord;

/**
 * Writes to Google Cloud Pub/Sub topic.
 *
 * @author Helio Dantas <helio.dantas@outlook.com>
 */
class GooglePubSubHandler extends AbstractProcessingHandler
{
    /** 10 MB in bytes - maximum message size in Pub/Sub */
    protected const MAX_MESSAGE_SIZE = 10485760;
    /** 1 MB in bytes - head message size for truncated messages */
    protected const HEAD_MESSAGE_SIZE = 1048576;

    private PubSubClient $client;
    private Topic $topic;
    private string $topicName;
    private array $attributes;

    public function __construct(
        PubSubClient $pubSubClient,
        string $topicName,
        array $attributes = [],
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $this->client = $pubSubClient;
        $this->topicName = $topicName;
        $this->attributes = $attributes;
        $this->topic = $this->client->topic($topicName);
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        if (!isset($record->formatted) || 'string' !== \gettype($record->formatted)) {
            throw new \InvalidArgumentException('GooglePubSubHandler accepts only formatted records as a string' . Utils::getRecordMessageForException($record));
        }

        $messageBody = $record->formatted;
        $messageAttributes = $this->attributes;

        // Add log level as attribute
        $messageAttributes['log_level'] = $record->level->getName();
        $messageAttributes['channel'] = $record->channel;
        $messageAttributes['datetime'] = $record->datetime->format('c');

        // Add context and extra data as attributes if they exist
        if (!empty($record->context)) {
            $messageAttributes['context'] = json_encode($record->context);
        }
        if (!empty($record->extra)) {
            $messageAttributes['extra'] = json_encode($record->extra);
        }

        // Truncate message if it exceeds maximum size
        if (\strlen($messageBody) >= static::MAX_MESSAGE_SIZE) {
            $messageBody = Utils::substr($messageBody, 0, static::HEAD_MESSAGE_SIZE);
            $messageAttributes['truncated'] = 'true';
            $messageAttributes['original_size'] = (string) \strlen($record->formatted);
        }

        try {
            $this->topic->publish([
                'data' => $messageBody,
                'attributes' => $messageAttributes,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't throw to avoid breaking the application
            error_log('GooglePubSubHandler failed to publish message: ' . $e->getMessage());
        }
    }

    /**
     * Get the topic name
     */
    public function getTopicName(): string
    {
        return $this->topicName;
    }

    /**
     * Get the current attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set additional attributes for messages
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
