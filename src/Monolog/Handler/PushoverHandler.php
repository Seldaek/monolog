<?php declare(strict_types=1);

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
use Monolog\Utils;
use Psr\Log\LogLevel;

/**
 * Sends notifications through the pushover api to mobile phones
 *
 * @author Sebastian Göttschkes <sebastian.goettschkes@googlemail.com>
 * @see    https://www.pushover.net/api
 *
 * @phpstan-import-type FormattedRecord from AbstractProcessingHandler
 * @phpstan-import-type Level from \Monolog\Logger
 * @phpstan-import-type LevelName from \Monolog\Logger
 */
class PushoverHandler extends SocketHandler
{
    /** @var string */
    private $token;
    /** @var array<int|string> */
    private $users;
    /** @var string */
    private $title;
    /** @var string|int|null */
    private $user = null;
    /** @var int */
    private $retry;
    /** @var int */
    private $expire;

    /** @var int */
    private $highPriorityLevel;
    /** @var int */
    private $emergencyLevel;
    /** @var bool */
    private $useFormattedMessage = false;

    /**
     * All parameters that can be sent to Pushover
     * @see https://pushover.net/api
     * @var array<string, bool>
     */
    private $parameterNames = [
        'token' => true,
        'user' => true,
        'message' => true,
        'device' => true,
        'title' => true,
        'url' => true,
        'url_title' => true,
        'priority' => true,
        'timestamp' => true,
        'sound' => true,
        'retry' => true,
        'expire' => true,
        'callback' => true,
    ];

    /**
     * Sounds the api supports by default
     * @see https://pushover.net/api#sounds
     * @var string[]
     */
    private $sounds = [
        'pushover', 'bike', 'bugle', 'cashregister', 'classical', 'cosmic', 'falling', 'gamelan', 'incoming',
        'intermission', 'magic', 'mechanical', 'pianobar', 'siren', 'spacealarm', 'tugboat', 'alien', 'climb',
        'persistent', 'echo', 'updown', 'none',
    ];

    /**
     * @param string       $token             Pushover api token
     * @param string|array $users             Pushover user id or array of ids the message will be sent to
     * @param string|null  $title             Title sent to the Pushover API
     * @param bool         $useSSL            Whether to connect via SSL. Required when pushing messages to users that are not
     *                                        the pushover.net app owner. OpenSSL is required for this option.
     * @param string|int   $highPriorityLevel The minimum logging level at which this handler will start
     *                                        sending "high priority" requests to the Pushover API
     * @param string|int   $emergencyLevel    The minimum logging level at which this handler will start
     *                                        sending "emergency" requests to the Pushover API
     * @param int          $retry             The retry parameter specifies how often (in seconds) the Pushover servers will
     *                                        send the same notification to the user.
     * @param int          $expire            The expire parameter specifies how many seconds your notification will continue
     *                                        to be retried for (every retry seconds).
     *
     * @phpstan-param string|array<int|string>    $users
     * @phpstan-param Level|LevelName|LogLevel::* $highPriorityLevel
     * @phpstan-param Level|LevelName|LogLevel::* $emergencyLevel
     */
    public function __construct(
        string $token,
        $users,
        ?string $title = null,
        $level = Logger::CRITICAL,
        bool $bubble = true,
        bool $useSSL = true,
        $highPriorityLevel = Logger::CRITICAL,
        $emergencyLevel = Logger::EMERGENCY,
        int $retry = 30,
        int $expire = 25200
    ) {
        $connectionString = $useSSL ? 'ssl://api.pushover.net:443' : 'api.pushover.net:80';
        parent::__construct($connectionString, $level, $bubble);

        $this->token = $token;
        $this->users = (array) $users;
        $this->title = $title ?: (string) gethostname();
        $this->highPriorityLevel = Logger::toMonologLevel($highPriorityLevel);
        $this->emergencyLevel = Logger::toMonologLevel($emergencyLevel);
        $this->retry = $retry;
        $this->expire = $expire;
    }

    protected function generateDataStream(array $record): string
    {
        $content = $this->buildContent($record);

        return $this->buildHeader($content) . $content;
    }

    /**
     * @phpstan-param FormattedRecord $record
     */
    private function buildContent(array $record): string
    {
        // Pushover has a limit of 512 characters on title and message combined.
        $maxMessageLength = 512 - strlen($this->title);

        $message = ($this->useFormattedMessage) ? $record['formatted'] : $record['message'];
        $message = Utils::substr($message, 0, $maxMessageLength);

        $timestamp = $record['datetime']->getTimestamp();

        $dataArray = [
            'token' => $this->token,
            'user' => $this->user,
            'message' => $message,
            'title' => $this->title,
            'timestamp' => $timestamp,
        ];

        if (isset($record['level']) && $record['level'] >= $this->emergencyLevel) {
            $dataArray['priority'] = 2;
            $dataArray['retry'] = $this->retry;
            $dataArray['expire'] = $this->expire;
        } elseif (isset($record['level']) && $record['level'] >= $this->highPriorityLevel) {
            $dataArray['priority'] = 1;
        }

        // First determine the available parameters
        $context = array_intersect_key($record['context'], $this->parameterNames);
        $extra = array_intersect_key($record['extra'], $this->parameterNames);

        // Least important info should be merged with subsequent info
        $dataArray = array_merge($extra, $context, $dataArray);

        // Only pass sounds that are supported by the API
        if (isset($dataArray['sound']) && !in_array($dataArray['sound'], $this->sounds)) {
            unset($dataArray['sound']);
        }

        return http_build_query($dataArray);
    }

    private function buildHeader(string $content): string
    {
        $header = "POST /1/messages.json HTTP/1.1\r\n";
        $header .= "Host: api.pushover.net\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "\r\n";

        return $header;
    }

    protected function write(array $record): void
    {
        foreach ($this->users as $user) {
            $this->user = $user;

            parent::write($record);
            $this->closeSocket();
        }

        $this->user = null;
    }

    /**
     * @param int|string $value
     *
     * @phpstan-param Level|LevelName|LogLevel::* $value
     */
    public function setHighPriorityLevel($value): self
    {
        $this->highPriorityLevel = Logger::toMonologLevel($value);

        return $this;
    }

    /**
     * @param int|string $value
     *
     * @phpstan-param Level|LevelName|LogLevel::* $value
     */
    public function setEmergencyLevel($value): self
    {
        $this->emergencyLevel = Logger::toMonologLevel($value);

        return $this;
    }

    /**
     * Use the formatted message?
     */
    public function useFormattedMessage(bool $value): self
    {
        $this->useFormattedMessage = $value;

        return $this;
    }
}
