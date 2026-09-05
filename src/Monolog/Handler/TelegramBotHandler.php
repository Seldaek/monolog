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
use Monolog\Level;
use Monolog\Utils;
use Monolog\LogRecord;

/**
 * Handler sends logs to Telegram using Telegram Bot API.
 *
 * How to use:
 *  1) Create a Telegram bot with https://telegram.me/BotFather;
 *  2) Create a Telegram channel or a group where logs will be recorded;
 *  3) Add the created bot from step 1 to the created channel/group from step 2.
 *
 * In order to create an instance of TelegramBotHandler use
 *  1. The Telegram bot API key from step 1
 *  2. The channel name with the `@` prefix if you created a public channel (e.g. `@my_public_channel`),
 *     or the channel ID with the `-100` prefix if you created a private channel (e.g. `-1001234567890`),
 *     or the group ID from step 2 (e.g. `-1234567890`).
 *
 * @link https://core.telegram.org/bots/api
 *
 * @author Mazur Alexandr <alexandrmazur96@gmail.com>
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
     */
    private string $apiKey;

    /**
     * Telegram channel name.
     * Since to start with '@' symbol as prefix.
     */
    private string $channel;

    /**
     * The kind of formatting that is used for the message.
     * See available options at https://core.telegram.org/bots/api#formatting-options
     * or in AVAILABLE_PARSE_MODES
     */
    private string|null $parseMode;

    /**
     * Disables link previews for links in the message.
     */
    private bool|null $disableWebPagePreview;

    /**
     * Sends the message silently. Users will receive a notification with no sound.
     */
    private bool|null $disableNotification;

    /**
     * True - split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages.
     * False - truncates a message that is too long.
     */
    private bool $splitLongMessages;

    /**
     * Adds 1-second delay between sending a split message (according to Telegram API to avoid 429 Too Many Requests).
     */
    private bool $delayBetweenMessages;

    /**
     * Telegram message thread id, unique identifier for the target message thread (topic) of the forum; for forum supergroups only
     * See how to get the `message_thread_id` https://stackoverflow.com/a/75178418
     */
    private int|null $topic;

    /**
     * @param  string                    $apiKey               Telegram bot access token provided by BotFather
     * @param  string                    $channel              Telegram channel name
     * @param  bool                      $splitLongMessages    Split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages
     * @param  bool                      $delayBetweenMessages Adds delay between sending a split message according to Telegram API
     * @param  int                       $topic                Telegram message thread id, unique identifier for the target message thread (topic) of the forum
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(
        string $apiKey,
        string $channel,
        $level = Level::Debug,
        bool   $bubble = true,
        ?string $parseMode = null,
        ?bool   $disableWebPagePreview = null,
        ?bool   $disableNotification = null,
        bool   $splitLongMessages = false,
        bool   $delayBetweenMessages = false,
        ?int   $topic = null
    ) {
        if (!\extension_loaded('curl')) {
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
        $this->setTopic($topic);
    }

    /**
     * @return $this
     */
    public function setParseMode(string|null $parseMode = null): self
    {
        if ($parseMode !== null && !\in_array($parseMode, self::AVAILABLE_PARSE_MODES, true)) {
            throw new \InvalidArgumentException('Unknown parseMode, use one of these: ' . implode(', ', self::AVAILABLE_PARSE_MODES) . '.');
        }

        $this->parseMode = $parseMode;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableWebPagePreview(bool|null $disableWebPagePreview = null): self
    {
        $this->disableWebPagePreview = $disableWebPagePreview;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableNotification(bool|null $disableNotification = null): self
    {
        $this->disableNotification = $disableNotification;

        return $this;
    }

    /**
     * True - split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages.
     * False - truncates a message that is too long.
     *
     * @return $this
     */
    public function splitLongMessages(bool $splitLongMessages = false): self
    {
        $this->splitLongMessages = $splitLongMessages;

        return $this;
    }

    /**
     * Adds 1-second delay between sending a split message (according to Telegram API to avoid 429 Too Many Requests).
     *
     * @return $this
     */
    public function delayBetweenMessages(bool $delayBetweenMessages = false): self
    {
        $this->delayBetweenMessages = $delayBetweenMessages;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTopic(?int $topic = null): self
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function handleBatch(array $records): void
    {
        $messages = [];

        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }

            if (\count($this->processors) > 0) {
                $record = $this->processRecord($record);
            }

            $messages[] = $record;
        }

        if (\count($messages) > 0) {
            $this->send((string) $this->getFormatter()->formatBatch($messages));
        }
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record->formatted);
    }

    /**
     * Send request to @link https://api.telegram.org/bot on SendMessage action.
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

    /**
     * Returns the Telegram Bot API base URL.
     * Override in a subclass to point to a self-hosted Bot API server.
     */
    protected function getBotApiUrl(): string
    {
        return self::BOT_API;
    }

    /**
     * Returns extra HTTP headers to send with every Telegram API request.
     * Override in a subclass to inject custom headers (e.g. auth tokens, tracing).
     *
     * @return string[]
     */
    protected function getCurlHeaders(): array
    {
        return [];
    }

    protected function sendCurl(string $message): void
    {
        if ('' === trim($message)) {
            return;
        }

        $ch = curl_init();
        $url = $this->getBotApiUrl() . $this->apiKey . '/SendMessage';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $headers = $this->getCurlHeaders();
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $params = [
            'text' => $message,
            'chat_id' => $this->channel,
            'parse_mode' => $this->parseMode,
            'disable_web_page_preview' => $this->disableWebPagePreview,
            'disable_notification' => $this->disableNotification,
        ];
        if ($this->topic !== null) {
            $params['message_thread_id'] = $this->topic;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $this->validateApiResponse(Curl\Util::execute($ch));
    }

    /**
     * @param  string|bool      $rawResult Raw response body from the Telegram API call
     * @throws RuntimeException When the response is missing, non-JSON, or signals failure
     */
    protected function validateApiResponse(string|bool $rawResult): void
    {
        if (!\is_string($rawResult)) {
            throw new RuntimeException('Telegram API error. Description: No response');
        }

        $result = json_decode($rawResult, true);

        if (!\is_array($result)) {
            throw new RuntimeException('Telegram API error. Description: Unexpected non-JSON response');
        }
        if (($result['ok'] ?? null) !== true) {
            throw new RuntimeException('Telegram API error. Description: ' . ($result['description'] ?? 'Unknown error'));
        }
    }

    /**
     * Handle a message that is too long: truncates or splits into several
     * @return string[]
     */
    private function handleMessageLength(string $message): array
    {
        $truncatedMarker = ' (…truncated)';
        if (!$this->splitLongMessages && \strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $maxLength = self::MAX_MESSAGE_LENGTH - \strlen($truncatedMarker);
            $truncated = Utils::substr($message, 0, $maxLength);

            if ($this->parseMode === 'HTML') {
                $truncated = $this->trimPartialHtmlTag($truncated);
                $closing = $this->closingHtmlTagsFor($this->updateOpenHtmlTags($truncated, []));

                while ($truncated !== '' && \strlen($truncated . $closing) > $maxLength) {
                    $truncated = $this->trimPartialHtmlTag(Utils::substr($truncated, 0, -1));
                    $closing = $this->closingHtmlTagsFor($this->updateOpenHtmlTags($truncated, []));
                }

                $truncated .= $closing;
            }

            return [$truncated . $truncatedMarker];
        }

        if ($this->parseMode === 'HTML' && \strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $this->splitHtmlMessage($message, self::MAX_MESSAGE_LENGTH);
        }

        return str_split($message, self::MAX_MESSAGE_LENGTH);
    }

    /**
     * Splits an HTML $message into chunks of at most $maxLength characters, closing any
     * tag left open at the end of a chunk and reopening it at the start of the next one,
     * so that neither a tag nor a start/end tag pair is ever cut in half.
     *
     * @return string[]
     */
    private function splitHtmlMessage(string $message, int $maxLength): array
    {
        $chunks = [];
        $openTags = [];
        $remaining = $message;

        while ($remaining !== '') {
            $prefix = $this->openingHtmlTagsFor($openTags);
            $slice = Utils::substr($remaining, 0, max(1, $maxLength - \strlen($prefix)));
            $isLast = \strlen($slice) >= \strlen($remaining);

            if (!$isLast) {
                $slice = $this->trimPartialHtmlTag($slice);
            }

            $sliceOpenTags = $this->updateOpenHtmlTags($slice, $openTags);
            $suffix = $isLast ? '' : $this->closingHtmlTagsFor($sliceOpenTags);

            while (!$isLast && $slice !== '' && \strlen($prefix . $slice . $suffix) > $maxLength) {
                $slice = $this->trimPartialHtmlTag(Utils::substr($slice, 0, -1));
                $sliceOpenTags = $this->updateOpenHtmlTags($slice, $openTags);
                $suffix = $this->closingHtmlTagsFor($sliceOpenTags);
            }

            $chunks[] = $prefix . $slice . $suffix;
            $remaining = Utils::substr($remaining, \strlen($slice));
            $openTags = $isLast ? [] : $sliceOpenTags;
        }

        return $chunks;
    }

    /**
     * Backs off before a `<` that has no matching `>` yet, so $html never ends mid-tag.
     */
    private function trimPartialHtmlTag(string $html): string
    {
        $lastLt = strrpos($html, '<');
        $lastGt = strrpos($html, '>');

        if ($lastLt !== false && ($lastGt === false || $lastLt > $lastGt)) {
            return Utils::substr($html, 0, $lastLt);
        }

        return $html;
    }

    /**
     * Scans $html for tags, applying them onto $openTags, so the result is the set of
     * tags still open after $html (tags opened before $html that $html did not close,
     * plus any $html opened itself and did not close again).
     *
     * @param  array<array{name: string, raw: string}> $openTags
     * @return array<array{name: string, raw: string}>
     */
    private function updateOpenHtmlTags(string $html, array $openTags): array
    {
        if (0 === preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/', $html, $matches, PREG_SET_ORDER)) {
            return $openTags;
        }

        foreach ($matches as $match) {
            $name = strtolower($match[2]);
            if ($match[1] === '/') {
                for ($i = \count($openTags) - 1; $i >= 0; $i--) {
                    if ($openTags[$i]['name'] === $name) {
                        array_splice($openTags, $i, 1);
                        break;
                    }
                }
            } else {
                $openTags[] = ['name' => $name, 'raw' => $match[0]];
            }
        }

        return $openTags;
    }

    /**
     * @param  array<array{name: string, raw: string}> $openTags
     */
    private function closingHtmlTagsFor(array $openTags): string
    {
        $closing = '';
        foreach (array_reverse($openTags) as $tag) {
            $closing .= '</' . $tag['name'] . '>';
        }

        return $closing;
    }

    /**
     * @param  array<array{name: string, raw: string}> $openTags
     */
    private function openingHtmlTagsFor(array $openTags): string
    {
        $opening = '';
        foreach ($openTags as $tag) {
            $opening .= $tag['raw'];
        }

        return $opening;
    }
}
