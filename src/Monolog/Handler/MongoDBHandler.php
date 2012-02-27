<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Thomas Tourlourat <thomas@tourlourat.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;

/**
 * Logs to a MongoDB database.
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $syslog = new MongoDBHandler(new \Mongo("mongodb://localhost:27017"), "logs", "prod");
 *   $log->pushHandler($syslog);
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class MongoDBHandler extends AbstractProcessingHandler {

    private $mongoCollection;

    public function __construct(\Mongo $mongo, $database, $collection, $level = Logger::DEBUG, $bubble = true) {
        $this->mongoCollection = $this->mongo->selectCollection($database, $collection);

        parent::__construct($level, $bubble);
    }

    protected function write(array $record) {
        unset($record["formatted"]);
        $this->mongoCollection->save($record);
    }

}