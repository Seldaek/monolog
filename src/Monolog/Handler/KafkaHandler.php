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

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RdKafka\TopicConf;

/**
 * Apache Kafka handler (https://kafka.apache.org/)
 *
 * Usage example:
 *
 *    $config = new RdKafka\Conf();
 *    $config->set('metadata.broker.list', '127.0.0.1');
 *    $producer = new RdKafka\Producer($config);
 *    $logger = new Logger('my_logger');
 *    $logger->pushHandler(new KafkaHandler($producer, 'test'));
 *
 *    $logger->info('My logger is now ready');
 *
 * @author Valentin Nazarov <i.kozlice@gmail.com>
 */
class KafkaHandler extends AbstractProcessingHandler
{
    /**
     * @var ProducerTopic
     */
    protected $topic;

    /**
     * @var Producer
     */
    protected $producer;

    /**
     * @param Producer $producer             Kafka message producer instance
     * @param string   $topicName            Kafka topic name (if it doesn't exist yet, will be created)
     * @param int      $level                The minimum logging level at which this handler will be triggered
     * @param bool     $bubble               Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Producer $producer, string $topicName, TopicConf $topicConfig = null, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->producer = $producer;
        if (!$topicConfig) {
            $topicConfig = new TopicConf();
        }
        $this->topic = $producer->newTopic($topicName, $topicConfig);
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     *
     * @return void
     */
    protected function write(array $record)
    {
        $data = (string)$record['formatted'];
        $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $data);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %context% %extra%');
    }
}
