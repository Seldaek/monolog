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
use Monolog\Formatter\UnderstandFormatter;

/**
 * Sends records to Understand.io
 *
 * Requirement is that CURL command line tool is installed and functioning correctly. 
 * To check whether CURL is available on your system, 
 * execute the following command in your console "curl -h"
 * If you see instructions on how to use CURL 
 * then your system has the CURL binary installed and you can use the async handler.
 * 
 * @author Aivis Silins <aivis.silins@gmail.com>
 * @see    https://www.understand.io/docs
 */
class UnderstandHandler extends AbstractProcessingHandler
{
    
    /**
     * Input key
     *
     * @var string
     */
    protected $inputKey;

    /**
     * API url
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * @param string $inputKey
     * @param string $apiUrl
     * @param integer $level
     * @param boolean $bubble
     */
    public function __construct($inputKey, $apiUrl = 'https://api.understand.io', $level = Logger::DEBUG, $bubble = true)
    {
        $this->inputKey = $inputKey;
        $this->apiUrl = $apiUrl;

        parent::__construct($level, $bubble);
    }

    /**
     * Serialize data and send to storage
     *
     * @param array $record
     * @return void
     */
    public function write(array $record)
    {
        $requestData = $record['formatted'];

        $this->send($requestData);
    }

    /**
     * Return endpoint
     *
     * @return string
     */
    protected function getEndpoint()
    {
        return implode('/', array($this->apiUrl, $this->inputKey));
    }
    
    /**
     * Send data 
     *
     * @param string $requestData
     * @return void
     */
    protected function send($requestData)
    {
        $parts = array(
            'curl',
            '-X POST',
            '-d',
            escapeshellarg($requestData),
            $this->getEndpoint(),
            '> /dev/null 2>&1 &'
        );

        $cmd = implode(' ', $parts);

        $this->exec($cmd);
    }

    /**
     * Execute call
     * 
     * @param string $cmd
     * @return void
     */
    protected function exec($cmd)
    {
        exec($cmd);
    }
    
    /**
     * Gets the default formatter.
     *
     * @return FormatterInterface
     */
    protected function getDefaultFormatter()
    {
        return new UnderstandFormatter();
    }
}
