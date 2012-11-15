<?php
/**
 * Created by JetBrains PhpStorm.
 * User: hissterkiller
 * Date: 11/15/12
 * Time: 8:42 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Monolog\Handler;

class ZeroMQHandler extends \Monolog\Handler\AbstractProcessingHandler
{

    /**
     * @var \ZMQSocket
     */
    private $connection;

    public function __construct(\ZMQSocket $connection, $level = \Monolog\Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        if (!$connection instanceof \ZMQSocket) {
            throw new \InvalidArgumentException("Connection is not the type of \\ZMQSocket!");
        }

        // Set default Formatter
        $this->setFormatter(new \Monolog\Formatter\JsonFormatter());
        $this->connection = $connection;
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
