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
 * Sends notifications through Slack API
 *
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @see    https://api.slack.com/
 */
class SlackHandler extends SocketHandler
{
    /**
     * Slack API token
     * @var string
     */
    private $token;

    /**
     * Slack channel (encoded ID or name)
     * @var string
     */
    private $channel;

    /**
     * Name of a bot
     * @var string
     */
    private $username;

    /**
     * Whether the message should be added to Slack as attachment (plain text otherwise)
     * @var bool
     */
    private $useAttachment;

    /**
     * @param string $token         Slack API token
     * @param string $channel       Slack channel (encoded ID or name)
     * @param string $username      Name of a bot
     * @param bool   $useAttachment Whether the message should be added to Slack as attachment (plain text otherwise)
     * @param int    $level         The minimum logging level at which this handler will be triggered
     * @param bool   $bubble        Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($token, $channel, $username = 'Monolog', $useAttachment = true, $level = Logger::CRITICAL, $bubble = true)
    {
        if (!extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required to use the SlackHandler');
        }

        parent::__construct('ssl://slack.com:443', $level, $bubble);

        $this->token = $token;
        $this->channel = $channel;
        $this->username = $username;
        $this->useAttachment = $useAttachment;
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
            'token' => $this->token,
            'channel' => $this->channel,
            'username' => $this->username,
            'text' => '',
            'attachments' => array()
        );

        if ($this->useAttachment) {
            $dataArray['attachments'] = json_encode(
                array(
                    array(
                        'fallback' => $record['message'],
                        'color' => $this->getAttachmentColor($record['level']),
                        'fields' => array(
                            array(
                                'title' => 'Message',
                                'value' => $record['message'],
                                'short' => false
                            ),
                            array(
                                'title' => 'Level',
                                'value' => $record['level_name'],
                                'short' => true
                            )
                        )
                    )
                )
            );
        } else {
            $dataArray['text'] = $record['message'];
        }

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
        $header = "POST /api/chat.postMessage HTTP/1.1\r\n";
        $header .= "Host: slack.com\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "\r\n";

        return $header;
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

    /**
     * Returned a Slack message attachment color associated with
     * provided level.
     *
     * @param  int     $level
     * @return string
     */
    protected function getAttachmentColor($level)
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return 'danger';
            case $level >= Logger::WARNING:
                return 'warning';
            case $level >= Logger::INFO:
                return 'good';
            default:
                return '#e3e4e6';
        }
    }
}
