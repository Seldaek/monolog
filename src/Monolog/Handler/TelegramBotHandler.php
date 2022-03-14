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
use Monolog\Utils;

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
 *
 * @phpstan-import-type Record from \Monolog\Logger
 */
class TelegramBotHandler extends AbstractProcessingHandler
{
    private const BOT_API = 'https://api.telegram.org/bot';

    /**
     * The available values of parseMode according to the Telegram api documentation
     */
    private const AVAILABLE_PARSE_MODES = [
        'HTML',
        'MarkdownV2',
        'Markdown', // legacy mode without underline and strikethrough, use MarkdownV2 instead
    ];

    /**
     * The maximum number of characters allowed in a message according to the Telegram api documentation
     */
    private const MAX_MESSAGE_LENGTH = 4096;

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
     * @var ?string
     */
    private $parseMode;

    /**
     * Disables link previews for links in the message.
     * @var ?bool
     */
    private $disableWebPagePreview;

    /**
     * Sends the message silently. Users will receive a notification with no sound.
     * @var ?bool
     */
    private $disableNotification;

    /**
     * True - split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages.
     * False - truncates a message that is too long.
     * @var bool
     */
    private $splitLongMessages;

    /**
     * Adds 1-second delay between sending a split message (according to Telegram API to avoid 429 Too Many Requests).
     * @var bool
     */
    private $delayBetweenMessages;

    /**
     * @param string $apiKey Telegram bot access token provided by BotFather
     * @param string $channel Telegram channel name
     * @param bool $splitLongMessages Split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages
     * @param bool $delayBetweenMessages Adds delay between sending a split message according to Telegram API
     * @throws MissingExtensionException
     */
    public function __construct(
        string $apiKey,
        string $channel,
               $level = Logger::DEBUG,
        bool   $bubble = true,
        string $parseMode = null,
        bool   $disableWebPagePreview = null,
        bool   $disableNotification = null,
        bool   $splitLongMessages = false,
        bool   $delayBetweenMessages = false
    )
    {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the TelegramBotHandler');
        }

        parent::__construct($level, $bubble);

        $this->apiKey = $apiKey;
        $this->channel = $channel;
        $this->setParseMode($parseMode);
        $this->disableWebPagePreview($disableWebPagePreview);
        $this->disableNotification($disableNotification);
        $this->splitLongMessages($splitLongMessages);
        $this->delayBetweenMessages($delayBetweenMessages);
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
     * True - split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages.
     * False - truncates a message that is too long.
     * @param bool $splitLongMessages
     * @return $this
     */
    public function splitLongMessages(bool $splitLongMessages = false): self
    {
        $this->splitLongMessages = $splitLongMessages;

        return $this;
    }

    /**
     * Adds 1-second delay between sending a split message (according to Telegram API to avoid 429 Too Many Requests).
     * @param bool $delayBetweenMessages
     * @return $this
     */
    public function delayBetweenMessages(bool $delayBetweenMessages = false): self
    {
        $this->delayBetweenMessages = $delayBetweenMessages;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records): void
    {
        /** @var Record[] $messages */
        $messages = [];

        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }

            if ($this->processors) {
                /** @var Record $record */
                $record = $this->processRecord($record);
            }

            $messages[] = $record;
        }

        if (!empty($messages)) {
            $this->send((string)$this->getFormatter()->formatBatch($messages));
        }
    }

    /**
     * @inheritDoc
     */
    protected function write(array $record): void
    {
        $this->send($record['formatted']);
    }

    /**
     * Send request to @link https://api.telegram.org/bot on SendMessage action.
     * @param string $message
     */
    protected function send(string $message): void
    {
        $messages = $this->handleMessageLength($message);

        foreach ($messages as $key => $msg) {
            if ($this->delayBetweenMessages && $key > 0) {
                sleep(1);
            }

            $this->sendCurl($msg);
        }
    }

    protected function sendCurl(string $message): void
    {
        $ch = curl_init();
        $url = self::BOT_API . $this->apiKey . '/SendMessage';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'text' => $message,
            'chat_id' => $this->channel,
            'parse_mode' => $this->parseMode,
            'disable_web_page_preview' => $this->disableWebPagePreview,
            'disable_notification' => $this->disableNotification,
        ]));

        $result = Curl\Util::execute($ch);
        if (!is_string($result)) {
            throw new RuntimeException('Telegram API error. Description: No response');
        }
        $result = json_decode($result, true);

        if ($result['ok'] === false) {
            throw new RuntimeException('Telegram API error. Description: ' . $result['description']);
        }
    }

    /**
     * Handle a message that is too long: truncates or splits into several
     * @param string $message
     * @return string[]
     */
    private function handleMessageLength(string $message): array
    {
        $truncatedMarker = ' (...truncated)';
        if (!$this->splitLongMessages && strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return [Utils::substr($message, 0, self::MAX_MESSAGE_LENGTH - strlen($truncatedMarker)) . $truncatedMarker];
        }

        return str_split($message, self::MAX_MESSAGE_LENGTH);
    }
}
