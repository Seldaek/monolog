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

use Monolog\Logger;
use Monolog\Formatter\NormalizerFormatter;

/**
 * Logs to a MongoDB database.
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $mongodb = new MongoDBHandler(new \Mongo("mongodb://localhost:27017"), "logs", "prod");
 *   $log->pushHandler($mongodb);
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class MongoDBHandler extends AbstractProcessingHandler
{
    protected $mongoCollection;
    protected $namespace;
    protected $manager;

    public function __construct($mongo, $database, $collection, $level = Logger::DEBUG, $bubble = true)
    {
        if (!($mongo instanceof \MongoClient || $mongo instanceof \Mongo || $mongo instanceof \MongoDB\Client || $mongo instanceof \MongoDB\Driver\Manager)) {
            throw new \InvalidArgumentException('MongoClient, Mongo or MongoDB\Client instance required');
        }
        $this->namespace = "$database.$collection";
        if($mongo instanceof \MongoDB\Driver\Manger) {
            $this->manager = $mongo;
        } else {
            $this->mongoCollection = $mongo->selectCollection($database, $collection);
        }

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if ($this->mongoCollection instanceof \MongoDB\Collection) {
            $this->mongoCollection->insertOne($record["formatted"]);
        } else if($this->mongoCollection instanceof \MongoCollection) {
            $this->mongoCollection->insert($record["formatted"]);
        } else {
            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->insert($record["formatted"]);
            $this->$manager->executeBulkWrite($this->namespace, $bulk);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }
}
