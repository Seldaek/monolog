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
    private const BOT_API = 'https://api.telegram.org/bot';

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
     * @param string $apiKey  Telegram bot access token provided by BotFather
     * @param string $channel Telegram channel name
     * @inheritDoc
     */
    public function __construct(
        string $apiKey,
        string $channel,
        $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $this->apiKey = $apiKey;
        $this->channel = $channel;
        $this->level = $level;
        $this->bubble = $bubble;
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
        $ch = curl_init();
        $url = self::BOT_API . $this->apiKey . '/SendMessage';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'text' => $message,
            'chat_id' => $this->channel,
        ]));

        $result = Curl\Util::execute($ch);
        $result = json_decode($result, true);

        if ($result['ok'] === false) {
            throw new RuntimeException('Telegram API error. Description: ' . $result['description']);
        }
    }
}
