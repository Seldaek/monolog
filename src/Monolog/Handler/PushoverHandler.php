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
 * Sends notifications through the pushover api to mobile phones
 *
 * @author Sebastian Göttschkes <sebastian.goettschkes@googlemail.com>
 * @see    https://www.pushover.net/api
 */
class PushoverHandler extends SocketHandler
{
    private $token;
    private $user;
    private $title;

    /**
     * @param string  $token  Pushover api token
     * @param string  $user   Pushover user id the message will be sent to
     * @param string  $title  Title sent to Pushover API
     * @param integer $level  The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($token, $user, $title = 'Monolog error', $level = Logger::CRITICAL, $bubble = true)
    {
        parent::__construct('api.pushover.net', $level, $bubble);
        $this->conntectionPort = 80;
        
        $this->token = $token;
        $this->user = $user;
        $this->title = $title;
    }

    /**
     * Build the content to be sent through the socket, then connect
     * (if necessary) and write to the socket
     *
     * @param array $record
     *
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     */
    public function write(array $record)
    {
        $data = $this->buildDataString($record);
        $content = $this->buildContent($data);
        $record['formatted'] = $content;

        parent::write($record);
    }

    private function buildContent($data)
    {
        $content = "POST /1/messages.json HTTP/1.1\r\n";
        $content .= "Host: api.pushover.net\r\n";
        $content .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $content .= "Content-Length: " . strlen($data) . "\r\n";
        $content .= "\r\n";
        $content .= $data;

        return $content;
    }

    private function buildDataString($record)
    {
        // Pushover has a limit of 512 characters on title and message combined.
        $maxMessageLength = 512 - strlen($this->title);
        $message = substr($record['message'], 0, $maxMessageLength);
        $timestamp = $record['datetime']->getTimestamp();

        $dataArray = array(
            'token' => $this->token,
            'user' => $this->user,
            'message' => $message,
            'title' => $this->title,
            'timestamp' => $timestamp
        );

        return http_build_query($dataArray);
    }
}