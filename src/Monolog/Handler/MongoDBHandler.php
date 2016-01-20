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
use MongoDB\Driver\Manager;
use MongoDB\Client;
//use Mongo;
use MongoClient;

/**
 * Logs to a MongoDB database.
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $mongodb = new MongoDBHandler(new \Mongo("mongodb://localhost:27017"), "logs", "prod");
 *   $log->pushHandler($mongodb);
 *
 * The above examples uses the MongoDB PHP library's client class; however,
 * classes from ext-mongodb (MongoDB\Driver\Manager) and ext-mongo (Mongo and
 * MongoClient) are also supported.
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class MongoDBHandler extends AbstractProcessingHandler
{
    private $mongoCollection;
    private $namespace;
    private $manager;


    /**
     * Constructor.
     *
     * @param Client|Manager|Mongo|MongoClient $mongo      MongoDB driver or library instance
     * @param string                           $database   Database name
     * @param string                           $collection Collection name
     * @param int                              $level      The minimum logging level at which this handler will be triggered
     * @param Boolean                          $bubble     Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($mongo, $database, $collection, $level = Logger::DEBUG, $bubble = true)
    {
        if (!($mongo instanceof MongoClient || $mongo instanceof \Mongo || $mongo instanceof MongoDB\Client || $mongo instanceof Manager)) {
            throw new \InvalidArgumentException('MongoClient, Mongo or MongoDB\Client instance required');
        }

        if ($mongo instanceof Manger) {
            $this->manager = $mongo;
            $this->namespace = $database . '.' . $collection;
        } else {
            $this->mongoCollection = $mongo->selectCollection($database, $collection);
        }

        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if ($this->mongoCollection instanceof Collection) {
            $this->mongoCollection->insertOne($record["formatted"]);

            return;
        }

        if ($this->mongoCollection instanceof MongoCollection) {
            $this->mongoCollection->insert($record["formatted"]);

            return;
        }

        // $this->manager instanceof \MongoDB\Driver\Manager
        $bulk = new BulkWrite();
        $bulk->insert($record["formatted"]);
        $this->$manager->executeBulkWrite($this->namespace, $bulk);

    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }
}
