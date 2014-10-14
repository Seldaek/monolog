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
     * Emoji icon name
     * @var string
     */
    private $iconEmoji;

    /**
     * Whether the message should be added to Slack as attachment (plain text otherwise)
     * @var bool
     */
    private $useAttachment;

    /**
     * Whether the the message that is added to Slack as attachment is in a short style (or not)
     * @var bool
     */
    private $useShortAttachment;

    /**
     * Whether the attachment should include extra data (or not)
     * @var bool
     */
    private $includeExtra;

    /**
     * @param string      $token         Slack API token
     * @param string      $channel       Slack channel (encoded ID or name)
     * @param string      $username      Name of a bot
     * @param bool        $useAttachment Whether the message should be added to Slack as attachment (plain text otherwise)
     * @param string|null $iconEmoji     The emoji name to use (or null)
     * @param int         $level         The minimum logging level at which this handler will be triggered
     * @param bool        $bubble        Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($token, $channel, $username = 'Monolog', $useAttachment = true, $iconEmoji = null, $level = Logger::CRITICAL, $bubble = true, $useShortAttachment = false, $includeExtra = false)
    {
        if (!extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required to use the SlackHandler');
        }

        parent::__construct('ssl://slack.com:443', $level, $bubble);

        $this->token = $token;
        $this->channel = $channel;
        $this->username = $username;
        $this->iconEmoji = trim($iconEmoji, ':');
        $this->useAttachment = $useAttachment;
        $this->useShortAttachment = $useShortAttachment;
        $this->includeExtra = $includeExtra;
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

        $extra = '';
        foreach ($record['extra'] as $var => $val) {
            $extra .= $var.': '.$this->replaceNewlines($this->convertToString($val))." | ";
        }

        $extra = rtrim($extra, " |");

        if ($this->useAttachment) {

            $attachment = array(
                'fallback' => $record['message'],
                'color' => $this->getAttachmentColor($record['level'])
            );

            if ($this->useShortAttachment) {
                $attachment['fields'] = array(
                    array(
                        'title' => $record['level_name'],
                        'value' => $record['message'],
                        'short' => false
                    )
                );
            } else {
                $attachment['fields'] = array(
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
                );
            }

            if ($this->includeExtra) {
                $attachment['fields'][] = array(
                    'title' => "Extra",
                    'value' => $extra,
                    'short' => false
                );
            }

            $dataArray['attachments'] = json_encode(array($attachment));
        } else {
            $dataArray['text'] = $record['message'];
        }

        if ($this->iconEmoji) {
            $dataArray['icon_emoji'] = ":{$this->iconEmoji}:";
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
    protected function write(array $record)
    {
        parent::write($record);
        $this->closeSocket();
    }

    /**
     * Returned a Slack message attachment color associated with
     * provided level.
     *
     * @param  int    $level
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

    /**
     * Copy from LineFormater (any better idea?)
     */
    protected function convertToString($data)
    {
        if (null === $data || is_bool($data)) {
            return var_export($data, true);
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            return $this->toJson($data, true);
        }

        return str_replace('\\/', '/', @json_encode($data));
    }


    /**
     * Copy from LineFormater (any better idea?)
     */
    protected function toJson($data, $ignoreErrors = false)
    {
        // suppress json_encode errors since it's twitchy with some inputs
        if ($ignoreErrors) {
            if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
                return @json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return @json_encode($data);
        }

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode($data);
    }

    /**
     * Copy from LineFormater (any better idea?)
     */
    protected function replaceNewlines($str)
    {
        return strtr($str, array("\r\n" => ' ', "\r" => ' ', "\n" => ' '));
    }
}
