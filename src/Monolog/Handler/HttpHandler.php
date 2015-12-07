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
use Monolog\Handler\AbstractProcessingHandler;

/**
 * A simple Http handler which logs to a remote server, which exposes an API for logging purposes.
 *
 * @author Parag Dakle <parag.dakle@gmail.com>
 */
class HttpHandler extends AbstractProcessingHandler
{
    
    /**
     * The URL of the API being exposed for logging
     */
    private $apiURL;
    
    /** 
     * The additional fields along with the log that need to be passed as parameters.
     */
    private $apiParametersArray;
    
    /**
     * The name of the key field whose value will contain the log record.
     */
    private $logMessageKeyName;


    public function __construct($url = null, $apiParametersArray = array(), $logMessageKeyName = "message", $level = Logger::DEBUG, $bubble = true)
    {
        $this->apiURL = $url;
        $this->apiParametersArray = $apiParametersArray;
        $this->logMessageKeyName = $logMessageKeyName;
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $this->apiParametersArray[$this->logMessageKeyName] = $record;
        $this->makeRequest($this->apiParametersArray);
    }

    protected function makeRequest($curlPostData)
    {
        $data = json_encode($curlPostData);
        $headers = array('Content-Type: application/json', 'Content-Length: ' . strlen($data));
        
        $curl = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $this->apiURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        curl_setopt($curl, CURLOPT_HEADER, true);
        
        Curl\Util::execute($curl);
    }
}
