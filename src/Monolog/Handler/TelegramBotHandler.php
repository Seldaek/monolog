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

use RuntimeException;
use Monolog\Logger;

/**
 * Handler send logs to Telegram using Telegram Bot API.
 *
 * How to use:
 *  1) Create telegram bot with https://telegram.me/BotFather
 *  2) Create a telegram channel where logs will be recorded.
 *  3) Add created bot from step 1 to the created channel from step 2.
 *
 * Use telegram bot API key from step 1 and channel name with '@' prefix from step 2 to create instance of TelegramBotHandler
 *
 * @link https://core.telegram.org/bots/api
 *
 * @author Mazur Alexandr <alexandrmazur96@gmail.com>
 */
class TelegramBotHandler extends AbstractProcessingHandler
{
    public const MESSAGE_LENGTH_LIMIT = 4096;
    public const TRIM_TOO_LONG_MESSAGE = 'trim';
    public const SPLIT_TOO_LONG_MESSAGE_INTO_MULTIPLE_MESSAGES = 'split';
    public const DO_NOTHING_WITH_TOO_LONG_MESSAGE = 'nothing';

    private const BOT_API = 'https://api.telegram.org/bot';

    /**
     * @var array AVAILABLE_PARSE_MODES The available values of parseMode according to the Telegram api documentation
     */
    private const AVAILABLE_PARSE_MODES = [
        'HTML',
        'MarkdownV2',
        'Markdown' // legacy mode without underline and strikethrough, use MarkdownV2 instead
    ];

    /**
     * Telegram bot access token provided by BotFather.
     * Create telegram bot with https://telegram.me/BotFather and use access token from it.
     * @var string
     */
    private $apiKey;

    /**
     * Telegram channel name.
     * Since to start with '@' symbol as prefix.
     * @var string
     */
    private $channel;

    /**
     * The kind of formatting that is used for the message.
     * See available options at https://core.telegram.org/bots/api#formatting-options
     * or in AVAILABLE_PARSE_MODES
     * @var string|null
     */
    private $parseMode;

    /**
     * Disables link previews for links in the message.
     * @var bool|null
     */
    private $disableWebPagePreview;

    /**
     * Sends the message silently. Users will receive a notification with no sound.
     * @var bool|null
     */
    private $disableNotification;

    /**
     * Describes the strategy by which too long messages will be processed.
     * @var string
     */
    private $tooLongMessageHandlingStrategy;

    /**
     * @param string $apiKey  Telegram bot access token provided by BotFather
     * @param string $channel Telegram channel name
     * @param string $tooLongMessageHandlingStrategy Describes what to do with too long messages
     * @inheritDoc
     */
    public function __construct(
        string $apiKey,
        string $channel,
        $level = Logger::DEBUG,
        bool $bubble = true,
        string $parseMode = null,
        bool $disableWebPagePreview = null,
        bool $disableNotification = null,
        string $tooLongMessageHandlingStrategy = self::DO_NOTHING_WITH_TOO_LONG_MESSAGE
    ) {
        parent::__construct($level, $bubble);

        $this->apiKey = $apiKey;
        $this->channel = $channel;
        $this->setParseMode($parseMode);
        $this->disableWebPagePreview($disableWebPagePreview);
        $this->disableNotification($disableNotification);
        $this->tooLongMessageHandlingStrategy = $tooLongMessageHandlingStrategy;
    }

    public function setParseMode(string $parseMode = null): self
    {
        if ($parseMode !== null && !in_array($parseMode, self::AVAILABLE_PARSE_MODES)) {
            throw new \InvalidArgumentException('Unknown parseMode, use one of these: ' . implode(', ', self::AVAILABLE_PARSE_MODES) . '.');
        }

        $this->parseMode = $parseMode;
        return $this;
    }

    public function disableWebPagePreview(bool $disableWebPagePreview = null): self
    {
        $this->disableWebPagePreview = $disableWebPagePreview;
        return $this;
    }

    public function disableNotification(bool $disableNotification = null): self
    {
        $this->disableNotification = $disableNotification;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): void
    {
        $messages = [];

        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }

            if ($this->processors) {
                $record = $this->processRecord($record);
            }

            $messages[] = $record;
        }

        if (!empty($messages)) {
            $batchMessage = (string) $this->getFormatter()->formatBatch($messages);
            $messagesToSend = $this->handleTooLongMessage($batchMessage);
            foreach ($messagesToSend as $message) {
                $this->send($message);
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function write(array $record): void
    {
        $message = $record['formatted'];
        $messages = $this->handleTooLongMessage($message);

        foreach ($messages as $message) {
            $this->send($message);
        }
    }

    /**
     * Send request to @link https://api.telegram.org/bot on SendMessage action.
     *
     * @param string $message
     */
    protected function send(string $message): void
    {
        $ch = $this->prepareCurlHandle($message);
        $result = Curl\Util::execute($ch);
        $result = json_decode($result, true);

        if ($result['ok'] === false) {
            throw new RuntimeException('Telegram API error. Description: ' . $result['description']);
        }
    }

    /**
     * Create a curl resource for further requesting Telegram API.
     *
     * @param string $message
     * @return CurlHandle|false|resource
     */
    protected function prepareCurlHandle(string $message)
    {
        $ch = curl_init();
        $url = self::BOT_API . $this->apiKey . '/SendMessage';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            http_build_query(
                [
                    'text' => $message,
                    'chat_id' => $this->channel,
                    'parse_mode' => $this->parseMode,
                    'disable_web_page_preview' => $this->disableWebPagePreview,
                    'disable_notification' => $this->disableNotification,
                ]
            )
        );

        return $ch;
    }

    /**
     * Handle too long message.
     *
     * @return string[]
     */
    protected function handleTooLongMessage(string $message): array
    {
        // nothing to do with the message if message is inside in the limit
        if (function_exists('mb_strlen') && mb_strlen($message) <= self::MESSAGE_LENGTH_LIMIT) {
            return [$message];
        } elseif (strlen($message) <= self::MESSAGE_LENGTH_LIMIT) {
            return [$message];
        }

        // do something with the message if the message is outside of the limit.
        switch ($this->tooLongMessageHandlingStrategy) {
            case self::TRIM_TOO_LONG_MESSAGE:
                // the whole message will be trimmed to the maximum message length.
                if (function_exists('mb_substr')) {
                    return [mb_substr($message, 0, self::MESSAGE_LENGTH_LIMIT - 3) . '...'];
                }
                return [substr($message, 0, self::MESSAGE_LENGTH_LIMIT - 3) . '...'];
            case self::SPLIT_TOO_LONG_MESSAGE_INTO_MULTIPLE_MESSAGES:
                // the message will be chunked into separate messages with the maximum length limit.
                if (function_exists('mb_str_split')) {
                    $splittedMessages = mb_str_split($message, self::MESSAGE_LENGTH_LIMIT - 3);
                    if ($splittedMessages === false) {
                        throw new RuntimeException('Unable to split too long message');
                    }

                    $lastChunk = end($splittedMessages);
                    foreach ($splittedMessages as &$splittedMessage) {
                        if ($lastChunk === $splittedMessage) {
                            break;
                        }

                        $splittedMessage .= '...';
                    }
                }

                return str_split($message, self::MESSAGE_LENGTH_LIMIT);
            // It's most likely cause 'message is too long' exception from Telegram API.
            case self::DO_NOTHING_WITH_TOO_LONG_MESSAGE:
            default:
                return [$message];
        }
    }
}
