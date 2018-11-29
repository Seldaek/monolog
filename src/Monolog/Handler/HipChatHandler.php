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

/**
 * Sends notifications through the hipchat api to a hipchat room
 *
 * This handler only supports the API v2
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
     * The maximum allowed length for the message.
     */
    protected const MAXIMUM_MESSAGE_LENGTH = 9500;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $room;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $notify;

    /**
     * @var string
     */
    private $format;

    /**
     * @var string
     */
    private $host;

    /**
     * @param string      $token  HipChat API Token
     * @param string      $room   The room that should be alerted of the message (Id or Name)
     * @param string|null $name   Name used in the "from" field.
     * @param bool        $notify Trigger a notification in clients or not
     * @param string|int  $level  The minimum logging level at which this handler will be triggered
     * @param bool        $bubble Whether the messages that are handled can bubble up the stack or not
     * @param bool        $useSSL Whether to connect via SSL.
     * @param string      $format The format of the messages (default to text, can be set to html if you have html in the messages)
     * @param string      $host   The HipChat server hostname.
     */
    public function __construct(
        string $token,
        string $room,
        ?string $name = 'Monolog',
        bool $notify = false,
        $level = Logger::CRITICAL,
        bool $bubble = true,
        bool $useSSL = true,
        string $format = 'text',
        string $host = 'api.hipchat.com'
    ) {
        $connectionString = $useSSL ? 'ssl://'.$host.':443' : $host.':80';
        parent::__construct($connectionString, $level, $bubble);

        $this->token = $token;
        $this->name = $name;
        $this->notify = $notify;
        $this->room = $room;
        $this->format = $format;
        $this->host = $host;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataStream(array $record): string
    {
        $content = $this->buildContent($record);

        return $this->buildHeader($content) . $content;
    }

    /**
     * Builds the body of API call
     */
    private function buildContent(array $record): string
    {
        $dataArray = [
            'notify' => $this->notify ? 'true' : 'false',
            'message' => $record['formatted'],
            'message_format' => $this->format,
            'color' => $this->getAlertColor($record['level']),
        ];

        if (!$this->validateStringLength($dataArray['message'], static::MAXIMUM_MESSAGE_LENGTH)) {
            if (function_exists('mb_substr')) {
                $dataArray['message'] = mb_substr($dataArray['message'], 0, static::MAXIMUM_MESSAGE_LENGTH).' [truncated]';
            } else {
                $dataArray['message'] = substr($dataArray['message'], 0, static::MAXIMUM_MESSAGE_LENGTH).' [truncated]';
            }
        }

        // append the sender name if it is set
        // always append it if we use the v1 api (it is required in v1)
        if ($this->name !== null) {
            $dataArray['from'] = (string) $this->name;
        }

        return http_build_query($dataArray);
    }

    /**
     * Builds the header of the API Call
     */
    private function buildHeader(string $content): string
    {
        // needed for rooms with special (spaces, etc) characters in the name
        $room = rawurlencode($this->room);
        $header = "POST /v2/room/{$room}/notification?auth_token={$this->token} HTTP/1.1\r\n";

        $header .= "Host: {$this->host}\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "\r\n";

        return $header;
    }

    /**
     * Assigns a color to each level of log records.
     */
    protected function getAlertColor(int $level): string
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
     */
    protected function write(array $record): void
    {
        parent::write($record);
        $this->finalizeWrite();
    }

    /**
     * Finalizes the request by reading some bytes and then closing the socket
     *
     * If we do not read some but close the socket too early, hipchat sometimes
     * drops the request entirely.
     */
    protected function finalizeWrite(): void
    {
        $res = $this->getResource();
        if (is_resource($res)) {
            @fread($res, 2048);
        }
        $this->closeSocket();
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): void
    {
        if (count($records) == 0) {
            return;
        }

        $batchRecords = $this->combineRecords($records);

        foreach ($batchRecords as $batchRecord) {
            $this->handle($batchRecord);
        }
    }

    /**
     * Combines multiple records into one. Error level of the combined record
     * will be the highest level from the given records. Datetime will be taken
     * from the first record.
     */
    private function combineRecords(array $records): array
    {
        $batchRecord = null;
        $batchRecords = [];
        $messages = [];
        $formattedMessages = [];
        $level = 0;
        $levelName = null;
        $datetime = null;

        foreach ($records as $record) {
            $record = $this->processRecord($record);

            if ($record['level'] > $level) {
                $level = $record['level'];
                $levelName = $record['level_name'];
            }

            if (null === $datetime) {
                $datetime = $record['datetime'];
            }

            $messages[] = $record['message'];
            $messageStr = implode(PHP_EOL, $messages);
            $formattedMessages[] = $this->getFormatter()->format($record);
            $formattedMessageStr = implode('', $formattedMessages);

            $batchRecord = [
                'message'   => $messageStr,
                'formatted' => $formattedMessageStr,
                'context'   => [],
                'extra'     => [],
            ];

            if (!$this->validateStringLength($batchRecord['formatted'], static::MAXIMUM_MESSAGE_LENGTH)) {
                // Pop the last message and implode the remaining messages
                $lastMessage = array_pop($messages);
                $lastFormattedMessage = array_pop($formattedMessages);
                $batchRecord['message'] = implode(PHP_EOL, $messages);
                $batchRecord['formatted'] = implode('', $formattedMessages);

                $batchRecords[] = $batchRecord;
                $messages = [$lastMessage];
                $formattedMessages = [$lastFormattedMessage];

                $batchRecord = null;
            }
        }

        if (null !== $batchRecord) {
            $batchRecords[] = $batchRecord;
        }

        // Set the max level and datetime for all records
        foreach ($batchRecords as &$batchRecord) {
            $batchRecord = array_merge(
                $batchRecord,
                [
                    'level'      => $level,
                    'level_name' => $levelName,
                    'datetime'   => $datetime,
                ]
            );
        }

        return $batchRecords;
    }

    /**
     * Validates the length of a string.
     *
     * If the `mb_strlen()` function is available, it will use that, as HipChat
     * allows UTF-8 characters. Otherwise, it will fall back to `strlen()`.
     *
     * Note that this might cause false failures in the specific case of using
     * a valid name with less than 16 characters, but 16 or more bytes, on a
     * system where `mb_strlen()` is unavailable.
     */
    private function validateStringLength(string $str, int $length): bool
    {
        if (function_exists('mb_strlen')) {
            return (mb_strlen($str) <= $length);
        }

        return (strlen($str) <= $length);
    }
}
