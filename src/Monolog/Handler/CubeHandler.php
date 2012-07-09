<?php
 
/*
 * This file is part of the Monolog package.
 *
 * (c) Wan Chen <kami@kamisama.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Monolog\Handler;
 
use Monolog\Logger;
 
/**
 * Logs to Cube.
 * Cube url : http://square.github.com/cube/
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   // To use UDP
 *   $cube = new CubeHandler("udp://127.0.0.1:1180");
 *   // To use HTTP
 *   $cube = new CubeHandler("http://localhost:1080");
 *   $log->pushHandler($cube);
 *
 * Given host and port are cube's default
 *
 * @author Wan Chen <kami@kamisama.me>
 */
class CubeHandler extends AbstractProcessingHandler
{
	private $socket = null;
	private $curlConnection = null;
	private $urlScheme = null;
	private $acceptedUrlScheme = array('http', 'udp');
	
	
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
    	
    	if(!$urlInfos || !isset($urlInfos['scheme']) || !isset($urlInfos['host']) || !isset($urlInfos['port']))
    		throw new \UnexpectedValueException('URL "'.$url.'" is not valid');
    	
    	if (!in_array($urlInfos['scheme'], $this->acceptedUrlScheme))
    	{
    		throw new \UnexpectedValueException('Invalid ' . $urlInfos['scheme'] . ' protocol. Valid options are ' . implode(', ', $this->acceptedUrlScheme));
    	}
    	else
    		$this->urlScheme = $urlInfos['scheme'];
    	
    	switch($this->urlScheme)
    	{
    		case 'http' : $this->connectHttp($urlInfos['host'], $urlInfos['port']); break;
    		case 'udp' : $this->connectUdp($urlInfos['host'], $urlInfos['port']); break;
    		default;
    	}
    	
        parent::__construct($level, $bubble);
    }
    
    
    /**
     * Establish a connection to an UDP socket
     *
     * @throws LogicException when unable to connect to the socket
     */
    protected function connectUdp($host = '127.0.0.1', $port = 1180)
    {
    	if (!extension_loaded('sockets')) {
    		throw new \LogicException('The sockets extension is not loaded');
    	}
    	 
    	$this->socket = socket_create(AF_INET, SOCK_DGRAM, 0);
    	if (!$this->socket)
    		throw new \LogicException('Unable to create a socket');
    	 
    	$result = socket_connect($this->socket, $host, $port);
    	if (!$result)
    	{
    		throw new \LogicException('Unable to connect to the socket at ' . $host . ':' . $port);
    	}
    }
 
    
    /**
     * Establish a connection to a http server
     */
    protected function connectHttp($host = 'localhost', $port = 1080)
    {
    	$this->curlConnection = curl_init('http://'.$host.':'.$port.'/1.0/event/put');
    	
    	if(!$this->curlConnection)
    		throw new \LogicException('Unable to connect to ' . $host . ':' . $port);
    	
    	curl_setopt($this->curlConnection, CURLOPT_CUSTOMREQUEST, "POST");
    	curl_setopt($this->curlConnection, CURLOPT_RETURNTRANSFER, true);
    	
    }
    
    
    /**
     * Send Event to Cube
     *
     * This assumes that the mandatory fields :
     * - type is in $record['context']['type']
     * - data is $record['context']
     */
    protected function write(array $record)
    {
    	$date = $record['datetime'];
    	
    	$datas = array(
    			'type' => $record['context']['type'],
    			'time' => $date->format('Y-m-d H:i:s')
    			);
    	
    	unset($record['context']['type'], $record['datetime']);
    	
    	$datas['data'] = $record['context'];
    	$datas['data']['level'] = $record['level'];
    	
    	$datas = json_encode($datas);
    	
    	switch($this->urlScheme)
    	{
    		case 'http' :
    			curl_setopt($this->curlConnection, CURLOPT_POSTFIELDS, '['.$datas.']');
    			curl_setopt($this->curlConnection, CURLOPT_HTTPHEADER, array(
    					'Content-Type: application/json',
    					'Content-Length: ' . strlen('['.$datas.']'))
    			);
    			$result = curl_exec($this->curlConnection);
    			break;
    		case 'udp' : socket_send($this->socket, $datas, strlen($datas), 0);
    	}
    	
    	
    }
}