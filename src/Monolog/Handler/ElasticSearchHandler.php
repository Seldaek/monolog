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

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\ElasticaFormatter;
use Monolog\Logger;

/**
 * Elastic Search handler
 *
 * Usage example:
 *
 *    $client = new Elastica\Client();
 *    $options = array(
 *        'index' => 'elastic_index_name',
 *        'buffer_limit' => 100,
 *    );
 *    $esHandler = new ElasticSearchHandler($client, $options);
 *    $log = new Logger('application');
 *    $log->pushHandler($esHandler);
 *
 * @author Jelle Vink <jelle.vink@gmail.com>
 */
class ElasticSearchHandler extends BufferHandler
{
    /**
     * @var Elastica\Client
     */
    protected $esClient;

    /**
     * @var array Handler config options
     */
    protected $options = array();

    /**
     * @param Elastica\Client  $esClient Elastica Client object
     * @param array            $options  Handler configuration
     * @param integer          $level    The minimum logging level at which this handler will be triggered
     * @param Boolean          $bubble   Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(\Elastica\Client $esClient, array $options = array(), $level = Logger::DEBUG, $bubble = true)
    {
        $this->esClient = $esClient;
        $this->options = array_merge(
            array(
                'index'          => 'monolog',      // Elastic index name
                'type'           => 'log_message',  // Elastic document type
                'buffer_limit'   => 0,              // Buffer limit for bulk requests  - see BufferHandler
                'flush_overflow' => true,           // Flush buffer when limit reached - see BufferHandler
                'ignore_error'   => true,           // Suppress runtime exception on connection issues
            ),
            $options
        );
        parent::__construct($this, $this->options['buffer_limit'], $level, $bubble, $this->options['flush_overflow']);
    }

    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records)
    {
        $docs = array_map(array($this->getFormatter(), 'format'), $records);
        try {
            $this->esClient->addDocuments($docs);
        } catch (\Elastica\Exception\ClientException $e) {
            if (!$this->options['ignore_error']) {
                throw new \RuntimeException(
                    sprintf('Elastic Search error: %s', $e->getMessage())
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        if ($formatter instanceof ElasticaFormatter) {
            return parent::setFormatter($formatter);
        }
        throw new \RuntimeException('ElasticSearchHandler is only compatible with ElasticaFormatter');
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
    protected function getDefaultFormatter()
    {
        return new ElasticaFormatter($this->options['index'], $this->options['type']);
    }
}
