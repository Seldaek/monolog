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

class UdpHandler extends AbstractProcessingHandler
{
    private $udpClient;

    /**
     * @param int      $udpConnection
     * @param bool|int $level
     * @param bool     $bubble
     */
    public function __construct($udpConnection, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        if (is_resource($udpConnection)) {
            $this->udpClient = $udpConnection;
        } else {
            throw new \InvalidArgumentException("Connection is not a resource");
        }
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
        fwrite($this->udpClient, $record['formatted']);
    }

    /**
     * close the udp connection
     */
    public function close()
    {
        fclose($this->udpClient);
        unset($this->udpClient);
    }
}
