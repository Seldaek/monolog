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

use Elastica\Client as ElasticaClient;
use Elasticsearch\Client as ElasticSearchClient;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\ElasticaFormatter;
use Monolog\Logger;
use Elastica\Exception\ExceptionInterface;

/**
 * Elastic Search handler
 *
 * Usage example:
 *
 * $logger = new \Monolog\Logger('application',
 *     [
 *         new \Monolog\Handler\ElasticSearchHandler(
 *             new \Elastica\Client()
 *         ),
 *         new \Monolog\Handler\ElasticSearchHandler(
 *             \Elasticsearch\ClientBuilder::create()->build()
 *         ),
 *     ]
 * );
 *
 * @author Jelle Vink <jelle.vink@gmail.com>
 */
class ElasticSearchHandler extends AbstractProcessingHandler
{
    /**
     * @var ElasticaClient|ElasticSearchClient
     */
    protected $client;

    /**
     * @var array Handler config options
     */
    protected $options = [];

    /**
     * @param ElasticSearchClient|ElasticaClient    $client  Elastica Client object
     * @param array                                 $options Handler configuration
     * @param int                                   $level   The minimum logging level at which this handler will be triggered
     * @param Boolean                               $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($client, array $options = [], $level = Logger::DEBUG, $bubble = true)
    {
        if (!$client instanceof ElasticSearchClient
            && !$client instanceof ElasticaClient
        ) {
            throw new \InvalidArgumentException('Client should be an instance of \Elasticsearch\Client or \Elastica\Client');
        }

        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->options = array_merge(
            [
                'index'          => 'monolog',      // Elastic index name
                'type'           => 'record',       // Elastic document type
                'ignore_error'   => false,          // Suppress Elastica exceptions
            ],
            $options
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        if ($this->client instanceof ElasticSearchClient) {
            unset($record['formatted']);
            $this->bulkSend($record);
        }

        if ($this->client instanceof ElasticaClient) {
            $this->bulkSend([$record['formatted']]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        if ($formatter instanceof ElasticaFormatter
            && !$this->client instanceof ElasticaClient
        ) {
            throw new \InvalidArgumentException('\Elastica\Client is only compatible with ElasticaFormatter');
        }

        return parent::setFormatter($formatter);
    }

    /**
     * Getter options
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        if ($this->client instanceof ElasticaClient) {
            return new ElasticaFormatter($this->options['index'], $this->options['type']);
        }

        return parent::getDefaultFormatter();
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        if ($this->client instanceof ElasticSearchClient) {
            $this->bulkSend($records);
        }

        if ($this->client instanceof ElasticaClient) {
            $documents = $this->getFormatter()->formatBatch($records);
            $this->bulkSend($documents);
        }
    }

    /**
     * Use Elasticsearch bulk API to send list of documents
     * @param  array             $documents
     * @throws \RuntimeException
     */
    protected function bulkSend(array $documents)
    {
        try {
            if ($this->client instanceof ElasticSearchClient) {
                $this->client->index([
                    'index' => $this->options['index'],
                    'type' => $this->options['type'],
                    'body' => $documents,
                ]);
            }

            if ($this->client instanceof ElasticaClient) {
                $this->client->addDocuments($documents);
            }
        } catch (ExceptionInterface $e) {
            if (!$this->options['ignore_error']) {
                throw new \RuntimeException("Error sending messages to Elasticsearch", 0, $e);
            }
        }
    }
}
