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
 * Handler send logs to Discord using Discord Webhook.
 *
 * How to use:
 *  1) Create a discord server.
 *  2) Create a text channel inside the created server from step 1.
 *  3) Click on edit channel icon in front of the created channel from step 2.
 *  4) Click on `Integration` tab.
 *  5) Click on `Create Webhook` button.
 *  6) Click on `Create Webhook` button.
 *  7) Click on `Copy Webhook URL` button.
 *
 * Use Discord webhook url from step 7 to create instance of DiscordWebhookHandler
 *
 *
 * @author Erfan Ebrahimi <me@erfanebrahimi.ir> [https://erfanebrahimi.ir]
 *
 * @phpstan-import-type Record from \Monolog\Logger
 */
class DiscordWebhookHandler extends AbstractProcessingHandler
{
    private const WEBHOOK_API = 'https://discord.com/api/webhooks/';
    private const COLORS = [
        'DEBUG' => "707070",
        'INFO' => "00fff7",
        'NOTICE' => "105deb",
        'WARNING' => "fffb94",
        'ERROR' => "ffb700",
        'CRITICAL' => "ff7700",
        'ALERT' => "ff4d00",
        'EMERGENCY' => "ebf7b0",
        'API' => "e06df7",
    ];

    public const Markdown = "Markdown";
    public const Embed = "Embed";

    /**
     * URL of Webhook.
     * @var string
     */
    private $WEBHOOK_URL;

    /**
     * The available values of parseMode according to the Discord webhook documentation
     */
    private const AVAILABLE_PARSE_MODES = [
        'Markdown',
        'Embed',
    ];


    /**
     * The kind of formatting that is used for the message.
     * in AVAILABLE_PARSE_MODES
     * @var ?string
     */
    private $parseMode;

    /**
     * Username of writer message (optional).
     * @var string
     */
    private $username;

    /**
     * Avatar of writer message (optional).
     * @var string
     */
    private $avatar_url;

    /**
     * true if this is a TTS message (optional).
     * @var bool
     */
    private $tts;

    /**
     * @param string $WEBHOOK_URL Discord webhook URL
     * @param string $parseMode The kind of formatting that is used for the message.
     * @param string $username Username of writer message.
     * @param string $avatar_url Avatar of writer message.
     * @param bool $tts true if this is a TTS message.
     * @throws MissingExtensionException
     */
    public function __construct(
        string $WEBHOOK_URL,
               $level = Logger::DEBUG,
        bool   $bubble = true,
        string $parseMode = self::Markdown,
        string $username = 'Log',
        string $avatar_url = 'https://cdn.discordapp.com/attachments/667370472828043284/1020287124597133332/log.png',
        bool   $tts = false
    )
    {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DiscordWebhookHandler');
        }
        if ( ! substr($WEBHOOK_URL, 0, strlen(self::WEBHOOK_API)) == self::WEBHOOK_API ) {
            throw new MissingExtensionException('Webhook URL is not correct!');
        }
        parent::__construct($level, $bubble);
        $this->WEBHOOK_URL = $WEBHOOK_URL;
        $this->setParseMode($parseMode);
        $this->setUsername($username);
        $this->setAvatarUrl($avatar_url);
        $this->setTTS($tts);
    }

    public function setParseMode(string $parseMode = null): self
    {
        $parseMode = ucfirst(strtolower($parseMode));
        if ($parseMode !== null && !in_array($parseMode, self::AVAILABLE_PARSE_MODES)) {
            throw new \InvalidArgumentException('Unknown parseMode, use one of these: ' . implode(', ', self::AVAILABLE_PARSE_MODES) . '.');
        }
        $this->parseMode = $parseMode;
        return $this;
    }

    public function setUsername(string $username = 'Log'): self
    {
        $this->username = $username;
        return $this;
    }

    public function setAvatarUrl(string $avatar_url = 'https://cdn.discordapp.com/attachments/667370472828043284/1020287124597133332/log.png'): self
    {
        $this->avatar_url = $avatar_url;
        return $this;
    }

    public function setTTS(bool $tts = false): self
    {
        $this->tts = $tts;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function write(array $record): void
    {
        $this->sendCurl($record);
    }


    protected function sendCurl(array $record): void
    {
        $ch = curl_init();
        $url = $this->WEBHOOK_URL;
        $headers = [ 'Content-Type: application/json','charset=utf-8' ];
        $data = [
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'tts' => $this->tts
        ];
        if ( $this->parseMode == self::Markdown)
            $data['content'] = $record['formatted'];
        else {
            $embed = [
                'description' => $record['message']."\n\n**Date:** ".$record['datetime'],
                "title" => $record['channel'] .' ('.$record['level_name'].')',
                'color' => $this->color($record['level_name']),
                "type" => "rich",
                "fields" => null
            ];
            $record['context'] = $this->array_flatten($record['context']);
            foreach ( $record['context'] as $name => $value )
                $embed['fields'][] = ["name" => $name,"value" => $value,"inline" => true];

            $data['embeds'] = [$embed];
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = Curl\Util::execute($ch);
        if (!is_string($result)) {
            throw new RuntimeException('Discord Webhook error. Description: No response');
        }
        $result = json_decode($result, true);

        if (isset($result['message'])) {
            throw new RuntimeException('Discord Webhook error. Description: ' . $result['message']);
        }
    }

    private function color(string $level_name)
    {
        return hexdec(self::COLORS[$level_name] ?? "FFFFFF");
    }

    private function array_flatten($array , $separator = '.') : array
    {

        $return = array();
        foreach ($array as $key => $value) {
            if( is_object($value) )
                $value =  (array) $value;
            if (is_array($value)){
                foreach ($value as $valueKey => $valueValue) {
                    $value[$key.$separator.$valueKey] = $valueValue;
                    unset($value[$valueKey]);
                }
                $return = array_merge($return, $this->array_flatten($value,$separator));
            }
            else {
                $return[$key] = $value;
            }
        }
        return $return;
    }
}
