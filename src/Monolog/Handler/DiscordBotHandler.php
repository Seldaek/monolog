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
 * Sends notifications through a Discord bot
 *
 * @author Gregory Goijaerts <crecketgaming@gmail.com>
 * @see    https://discordapp.com/developers/docs/reference
 */
class DiscordBotHandler extends AbstractProcessingHandler
{
    /**
     * The channel ID
     * @var string
     */
    private $channelId;

    /**
     * Discord token
     * @var string
     */
    private $token;

    /**
     * Emoji to use
     * @var string
     */
    private $emoji;

    /**
     * User snowflake ID to mention in the message
     * @var string
     */
    private $userMention;

    /**
     * @param  string $channelId Discord channel snowflake ID
     * @param  string $token Discord bot token
     * @param  int $level The minimum logging level at which this handler will be triggered
     * @param  bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @param  string $userMention Mention a user in the message, has to be false or a valid user snowflake ID
     * @param  string $emoji prepend the entire message with a emoji
     */
    public function __construct($channelId, $token, $level = Logger::CRITICAL, $bubble = true, $userMention = null, $emoji = null)
    {
        parent::__construct($level, $bubble);

        $this->channelId = $channelId;
        $this->token = $token;
        $this->emoji = $emoji;
        $this->userMention = $userMention;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        $discordUrl = sprintf('https://discordapp.com/api/channels/%s/messages', $this->channelId);

        // Check if a usermention is enabled
        if ($this->userMention) {
            /**
             * Mention a user using the official message formatting
             * @see https://discordapp.com/developers/docs/resources/channel#message-formatting
             */
            $message = sprintf('%s<@!%s> %s: `%s`',
                $this->emoji,
                $this->userMention,
                Logger::getLevelName($record['level']),
                $record['message']
            );
        } else {
            // Only prepend the message with the emoji
            $message = sprintf('%s %s: `%s`',
                $this->emoji,
                Logger::getLevelName($record['level']),
                $record['message']
            );
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $discordUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bot ' . $this->token));
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'content' => $message
        ));
        //

        Curl\Util::execute($ch);
    }

    /**
     * Set the emoji icon
     *
     * @param $emoji
     */
    public function setEmoji($emoji)
    {
        $this->emoji = $emoji;
    }

    /**
     * Set the usermention snowflake Id
     *
     * @param $userMention
     */
    public function setUsermention($userMention)
    {
        $this->userMention = $userMention;
    }
}
