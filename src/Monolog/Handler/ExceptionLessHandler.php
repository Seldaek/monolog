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
use Monolog\Formatter\ExceptionLessFormatter;

/**
 * Sends errors to ExceptionLess.
 *
 * @author Israel Garcia <igarcia@nearsolutions.net>
 */
class ExceptionLessHandler extends AbstractProcessingHandler
{
    const HOST = 'https://www.exceptionless.io';
    const ENDPOINT_SINGLE = 'api/v2/events';

    

    protected $token;
    protected $host;

    protected $proxy_addr;
    protected $proxy_auth;
    protected $proxy_type;


    protected $tag = array();

    public function __construct($token, $hosturi = HOST, $level = Logger::DEBUG, $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use the ExceptionLessHandler');
        }
        $this->host = $hosturi;
        $this->token = $token;
        $this->proxy_type = CURLPROXY_HTTP;

        parent::__construct($level, $bubble);
    }

    public function setProxy($proxyaddr, $proxyauth, $proxytype = CURLPROXY_HTTP)
    {
        $this->proxy_addr = $proxyaddr;
        $this->proxy_auth = $proxyauth;
        $this->proxy_type = $proxytype;
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

        foreach ($records as $record) {
            $this->send($this->getFormatter()->format($record), self::ENDPOINT_SINGLE);
        }
    }

    protected function send($data, $endpoint)
    {
        $url = sprintf("%s/%s/", $this->host, $endpoint);

        $headers = array('Content-Type: application/json',
                         'Authorization: Bearer '.$this->token);

        $ch = curl_init();

        if (!empty($this->proxy_addr)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy_addr);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy_auth);
            curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy_type);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        

        Curl\Util::execute($ch);
    }

    protected function getDefaultFormatter()
    {
        return new ExceptionLessFormatter();
    }
}

