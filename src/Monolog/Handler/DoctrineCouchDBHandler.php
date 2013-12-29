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

use Doctrine\CouchDB\CouchDBClient;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;

/**
 * CouchDB handler for Doctrine CouchDB ODM
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class DoctrineCouchDBHandler extends AbstractProcessingHandler
{
    /**
     * @var CouchDBClient
     */
    private $client;

    /**
     * @param CouchDBClient $client
     * @param bool|int      $level
     * @param bool          $bubble
     */
    public function __construct(CouchDBClient $client, $level = Logger::DEBUG, $bubble = true)
    {
        $this->client = $client;
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $this->client->postDocument($record['formatted']);
    }

    /**
     * @return \Monolog\Formatter\FormatterInterface|NormalizerFormatter
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter;
    }
}
