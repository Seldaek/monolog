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

/**
 * Sends errors to Loggly.
 *
 * @author Przemek Sobstel <przemek@sobstel.org>
 */
class LogglyHandler extends AbstractProcessingHandler
{
    const HOST = 'logs-01.loggly.com';

    /**
     * @var int
     */
    protected $token;

    /**
     * @var string
     */
    protected $tag;

    /**
     * @param int      $token
     * @param bool|int $level
     * @param bool     $bubble
     *
     * @throws \LogicException
     */
    public function __construct($token, $level = Logger::DEBUG, $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use the LogglyHandler');
        }

        $this->token = $token;

        parent::__construct($level, $bubble);
    }

    /**
     * @param string $tag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
    }

    /**
     * @param array $record
     */
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

    /**
     * @return \Monolog\Formatter\FormatterInterface|JsonFormatter
     */
    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}
