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
 * Sends notifications through the hipchat api to a hipchat room
 *
 * Notes:
 * API token - HipChat API token
 * Room      - HipChat Room Id or name, where messages are sent
 * Name      - Name used to send the message (from)
 * notify    - Should the message trigger a notification in the clients
 *
 * @author Rafael Dohms <rafael@doh.ms>
 * @see    https://www.hipchat.com/docs/api
 */
class HipChatHandler extends SocketHandler
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var array
     */
    private $room;

    /**
     * @var string
     */
    private $name;

    /**
     * @var boolean
     */
    private $notify;

    /**
     * @param string  $token  HipChat API Token
     * @param string  $room   The room that should be alerted of the message (Id or Name)
     * @param string  $name   Name used in the "from" field
     * @param bool    $notify Trigger a notification in clients or not
     * @param int     $level  The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     * @param Boolean $useSSL Whether to connect via SSL.
     */
    public function __construct($token, $room, $name = 'Monolog', $notify = false, $level = Logger::CRITICAL, $bubble = true, $useSSL = true)
    {
        $connectionString = $useSSL ? 'ssl://api.hipchat.com:443' : 'api.hipchat.com:80';
        parent::__construct($connectionString, $level, $bubble);

        $this->token = $token;
        $this->name = $name;
        $this->notify = $notify;
        $this->room = $room;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array  $record
     * @return string
     */
    protected function generateDataStream($record)
    {
        $content = $this->buildContent($record);

        return $this->buildHeader($content) . $content;
    }

    /**
     * Builds the body of API call
     *
     * @param  array  $record
     * @return string
     */
    private function buildContent($record)
    {
        $dataArray = array(
            'from' => $this->name,
            'room_id' => $this->room,
            'notify' => $this->notify,
            'message' => $record['formatted'],
            'message_format' => 'text',
            'color' => $this->getAlertColor($record['level']),
        );

        return http_build_query($dataArray);
    }

    /**
     * Builds the header of the API Call
     *
     * @param  string $content
     * @return string
     */
    private function buildHeader($content)
    {
        $header = "POST /v1/rooms/message?format=json&auth_token=".$this->token." HTTP/1.1\r\n";
        $header .= "Host: api.hipchat.com\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "\r\n";

        return $header;
    }

    /**
     * Assigns a color to each level of log records.
     *
     * @param  integer $level
     * @return string
     */
    protected function getAlertColor($level)
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return 'red';
            case $level >= Logger::WARNING:
                return 'yellow';
            case $level >= Logger::INFO:
                return 'green';
            case $level == Logger::DEBUG:
                return 'gray';
            default:
                return 'yellow';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param array $record
     */
    public function write(array $record)
    {
        parent::write($record);
        $this->closeSocket();
    }

}
