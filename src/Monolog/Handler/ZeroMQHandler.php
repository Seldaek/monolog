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

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use \ZMQSocket;

class ZeroMQHandler extends AbstractProcessingHandler
{

    /**
     * @var \ZMQSocket
     */
    private $connection;

    public function __construct(\ZMQSocket $connection, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->connection = $connection;
    }

    /**
     * @return \Monolog\Formatter\JsonFormatter
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
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
        $this->connection->send($record['formatted'], \ZMQ::MODE_NOBLOCK);
    }
}
