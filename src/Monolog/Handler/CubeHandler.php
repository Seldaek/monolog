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
 
/**
 * Logs to Cube.
 * @link http://square.github.com/cube/
 *
 * @author Wan Chen <kami@kamisama.me>
 */
class CubeHandler extends AbstractProcessingHandler
{
    private $udpConnection = null;
    private $httpConnection = null;

    private $scheme = null;
    private $host = null;
    private $port = null;
    private $acceptedScheme = array('http', 'udp');
    
    
    /**
     * Create a Cube handler
     *
     * @throws UnexpectedValueException when given url is not a valid url.
     * A valid url must consists of three parts : protocol://host:port
     * Only valid protocol used by Cube are http and udp
     */
    public function __construct($url, $level = Logger::DEBUG, $bubble = true)
    {
        $urlInfos = parse_url($url);
        
        if (!$urlInfos || !isset($urlInfos['scheme']) 
            || !isset($urlInfos['host']) || !isset($urlInfos['port'])) {
            throw new \UnexpectedValueException('URL "'.$url.'" is not valid');
        }
        
        if (!in_array($urlInfos['scheme'], $this->acceptedScheme)) {
            throw new \UnexpectedValueException(
                'Invalid ' . $urlInfos['scheme']  . ' protocol.'
                . 'Valid options are ' . implode(', ', $this->acceptedScheme));
        } else {
            $this->scheme = $urlInfos['scheme'];
            $this->host = $urlInfos['host'];
            $this->port = $urlInfos['port'];
        }
        
        parent::__construct($level, $bubble);
    }
    

    /**
     * Check if a connection resource is available 
     *
     * @return boolean
    */
    private function isConnected($scheme)
    {
        return $this->{$scheme . 'Connection'} !== null;
    } 
    

    /**
     * Establish a connection to an UDP socket
     *
     * @throws LogicException when unable to connect to the socket
     */
    protected function connectUdp()
    {
        if (!extension_loaded('sockets')) {
            throw new \LogicException('The sockets extension is not loaded');
        }
         
        $this->udpConnection = socket_create(AF_INET, SOCK_DGRAM, 0);
        if (!$this->udpConnection) {
            throw new \LogicException('Unable to create a socket');
        }

        if (!socket_connect($this->udpConnection, $this->host, $this->port)) {
            throw new \LogicException('Unable to connect to the socket at ' 
                . $this->host . ':' . $this->port);
        }
    }
    

    /**
     * Establish a connection to a http server
     */
    protected function connectHttp()
    {
        $this->httpConnection = 
            curl_init('http://'.$this->host.':'.$this->port.'/1.0/event/put');
        
        if (!$this->httpConnection) {
            throw new \LogicException('Unable to connect to ' 
                . $this->host . ':' . $this->port);
        }

        curl_setopt($this->httpConnection, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->httpConnection, CURLOPT_RETURNTRANSFER, true);
        
    }
    
    
    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $date = $record['datetime'];
        
        $datas = array('time' => $date->format('Y-m-d H:i:s'));
        unset($record['datetime']);

        if (isset($record['context']['type'])) {
            $datas['type'] = $record['context']['type'];
            unset($record['context']['type']);
        }
        
        $datas['data'] = $record['context'];
        $datas['data']['level'] = $record['level'];
        
        call_user_func(
            array($this, 'send'.ucwords($this->scheme)), json_encode($datas));
    }


    private function sendUdp($datas)
    {
        if (!$this->isConnected($this->scheme)) {
            call_user_func(
                array($this, 'connect' . ucwords($this->scheme)));
        }

        socket_send($this->udpConnection, $datas, strlen($datas), 0);
    }


    private function sendHttp($datas)
    {
        if (!$this->isConnected($this->scheme)) {
            call_user_func(
                array($this, 'connect' . ucwords($this->scheme)));
        }

        curl_setopt($this->httpConnection, CURLOPT_POSTFIELDS, '['.$datas.']');
        curl_setopt($this->httpConnection, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen('['.$datas.']'))
        );
        return curl_exec($this->httpConnection);
    }
}