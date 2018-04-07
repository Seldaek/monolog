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

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\RuntimeException as ElasticSearchRuntimeException;
use InvalidArgumentException;
use Monolog\Formatter\ElasticSearchFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use RuntimeException;
use Throwable;

/**
 * Elastic Search handler
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html
 *
 * Simple usage example:
 *
 *    $client = \ElasticSearch\ClientBuilder::create()
 *        ->setHosts($hosts)
 *        ->build();
 *
 *    $options = array(
 *        'index' => 'elastic_index_name',
 *        'type' => 'elastic_doc_type',
 *    );
 *    $handler = new ElasticSearchHandler($client, $options);
 *    $log = new Logger('application');
 *    $log->pushHandler($handler);
 *
 * @author Avtandil Kikabidze <akalongman@gmail.com>
 */
class ElasticSearchHandler extends AbstractProcessingHandler
{
    /**
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * @var array Handler config options
     */
    protected $options = [];

    /**
     * @param \Elasticsearch\Client $client ElasticSearch Client object
     * @param array $options Handler configuration
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Client $client, array $options = [], $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->options = array_merge(
            [
                'index'        => 'monolog',      // Elastic index name
                'type'         => 'record',       // Elastic document type
                'ignore_error' => false,          // Suppress ElasticSearch exceptions
            ],
            $options
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $this->bulkSend([$record['formatted']]);
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        if ($formatter instanceof ElasticSearchFormatter) {
            return parent::setFormatter($formatter);
        }
        throw new InvalidArgumentException('ElasticSearchHandler is only compatible with ElasticSearchFormatter');
    }

    /**
     * Getter options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new ElasticSearchFormatter($this->options['index'], $this->options['type']);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        $documents = $this->getFormatter()->formatBatch($records);
        $this->bulkSend($documents);
    }

    /**
     * Use ElasticSearch bulk API to send list of documents
     *
     * @param  array $records
     * @throws \RuntimeException
     */
    protected function bulkSend(array $records)
    {
        try {
            $params = [
                'body' => [],
            ];

            foreach ($records as $record) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $record['_index'],
                        '_type'  => $record['_type'],
                    ],
                ];
                unset($record['_index'], $record['_type']);

                $params['body'][] = $record;
            }

            $responses = $this->client->bulk($params);

            if ($responses['errors'] === true) {
                throw new ElasticSearchRuntimeException('ElasticSearch returned error for one of the records');
            }
        } catch (Throwable $e) {
            if (! $this->options['ignore_error']) {
                throw new RuntimeException('Error sending messages to ElasticSearch', 0, $e);
            }
        }
    }
}
