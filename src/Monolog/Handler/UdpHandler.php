<?php
/**
 * Created by JetBrains PhpStorm.
 * User: hissterkiller
 * Date: 11/15/12
 * Time: 9:32 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Monolog\Handler;

class UdpHandler extends AbstractProcessingHandler
{
    private $udpClient;

    public function __construct($udpConnection, $level = \Monolog\Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        if (is_resource($udpConnection)) {
            $this->udpClient = $udpConnection;
            $this->setFormatter(new \Monolog\Formatter\JsonFormatter());
        } else {
            throw new \InvalidArgumentException("Connection is not a resource");
        }
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
        fwrite($this->udpClient, $record['formatted']);
    }

    public function close()
    {
        fclose($this->udpClient);
    }


}
