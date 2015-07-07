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
use Monolog\Formatter\LogglyFormatter;

/**
 * Sends errors to Loggly.
 *
 * @author Przemek Sobstel <przemek@sobstel.org>
 * @author Adam Pancutt <adam@pancutt.com>
 * @author Gregory Barchard <gregory@barchard.net>
 */
class LogglyHandler extends AbstractProcessingHandler
{
    const HOST = 'logs-01.loggly.com';
    const ENDPOINT_SINGLE = 'inputs';
    const ENDPOINT_BATCH = 'bulk';

    protected $token;

    protected $tag = array();

    public function __construct($token, $level = Logger::DEBUG, $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use the LogglyHandler');
        }

        $this->token = $token;

        parent::__construct($level, $bubble);
    }

    public function setTag($tag)
    {
        $tag = !empty($tag) ? $tag : array();
        $this->tag = is_array($tag) ? $tag : array($tag);
    }

    public function addTag($tag)
    {
        if (!empty($tag)) {
            $tag = is_array($tag) ? $tag : array($tag);
            $this->tag = array_unique(array_merge($this->tag, $tag));
        }
    }

    protected function write(array $record)
    {
        $this->send($record["formatted"], self::ENDPOINT_SINGLE);
    }

    public function handleBatch(array $records)
    {
        $level = $this->level;

        $records = array_filter($records, function ($record) use ($level) {
            return ($record['level'] >= $level);
        });

        if ($records) {
            $this->send($this->getFormatter()->formatBatch($records), self::ENDPOINT_BATCH);
        }
    }

    protected function send($data, $endpoint)
    {
        $url = sprintf("https://%s/%s/%s/", self::HOST, $endpoint, $this->token);

        $headers = array('Content-Type: application/json');

        if (!empty($this->tag)) {
            $headers[] = 'X-LOGGLY-TAG: '.implode(',', $this->tag);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $RETRY_COUNT = 5;  
        $TOTAL_RETRIES = 0;

        /*
        * Curl error code 6  : CURLE_COULDNT_RESOLVE_HOST  
        * Curl error code 7  : CURLE_COULDNT_CONNECT
        * Curl error code 9  : CURLE_REMOTE_ACCESS_DENIED 
        * Curl error code 22 : CURLE_HTTP_RETURNED_ERROR
        * Curl error code 25 : CURLE_UPLOAD_FAILED
        * Curl error code 26 : CURLE_READ_ERROR 
        * Curl error code 28 : CURLE_OPERATION_TIMEDOUT
        * Curl error code 34 : CURLE_HTTP_POST_ERROR 
        * Curl error code 35 : CURLE_SSL_CONNECT_ERROR 
        *
        * Curl Error Codes  : http://curl.haxx.se/libcurl/c/libcurl-errors.html
        */
        $CurlErrorCodesForRetries = array(6,7,9,22,25,26,28,34,35);

        do
        {
            $TOTAL_RETRIES = $TOTAL_RETRIES + 1;
            if (curl_exec($ch) === false) {
                /*
                    If the error cannot be controlled by retries or
                    total retry count is already completed then
                    show error and break the loop
                */
                if(in_array(curl_errno($ch),$CurlErrorCodesForRetries) === false 
                        || $TOTAL_RETRIES > $RETRY_COUNT){
                    echo sprintf('Curl error (code %s): %s', curl_errno($ch), curl_error($ch));
                    curl_close($ch);
                    break;
                }
            }
            else{
                curl_close($ch);
                break;
            }
        }while($TOTAL_RETRIES <= $RETRY_COUNT);
    }

    protected function getDefaultFormatter()
    {
        return new LogglyFormatter();
    }
}
