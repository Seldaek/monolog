<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Przemek Sobstel <przemek@sobstel.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Sends errors to Loggly.
 */
class LogglyHandler extends AbstractProcessingHandler
{
    const HOST = 'logs-01.loggly.com';

    protected $token;

    protected $tag;

    public function __construct($token, $level = Logger::DEBUG, $bubble = true)
    {
        $this->token = $token;

        parent::__construct($level, $bubble);
    }

    public function setTag($tag)
    {
        $this->tag = $tag;
    }

    protected function write(array $record)
    {
        $url = sprintf("http://%s/inputs/%s/", self::HOST, $this->token);
        if ($this->tag) {
            $url .= sprintf("tag/%s/", $this->tag);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $record["formatted"]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }

    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}
