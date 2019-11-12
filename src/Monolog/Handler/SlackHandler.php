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

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Monolog\Utils;
use Monolog\Handler\Slack\SlackRecord;

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
     * Instance of the SlackRecord util class preparing data for Slack API.
     * @var SlackRecord
     */
    private $slackRecord;

    /**
     * @param  string                    $token                  Slack API token
     * @param  string                    $channel                Slack channel (encoded ID or name)
     * @param  string|null               $username               Name of a bot
     * @param  bool                      $useAttachment          Whether the message should be added to Slack as attachment (plain text otherwise)
     * @param  string|null               $iconEmoji              The emoji name to use (or null)
     * @param  int                       $level                  The minimum logging level at which this handler will be triggered
     * @param  bool                      $bubble                 Whether the messages that are handled can bubble up the stack or not
     * @param  bool                      $useShortAttachment     Whether the the context/extra messages added to Slack as attachments are in a short style
     * @param  bool                      $includeContextAndExtra Whether the attachment should include context and extra data
     * @param  array                     $excludeFields          Dot separated list of fields to exclude from slack message. E.g. ['context.field1', 'extra.field2']
     * @throws MissingExtensionException If no OpenSSL PHP extension configured
     */
    public function __construct(
        string $token,
        string $channel,
        ?string $username = null,
        bool $useAttachment = true,
        ?string $iconEmoji = null,
        $level = Logger::CRITICAL,
        bool $bubble = true,
        bool $useShortAttachment = false,
        bool $includeContextAndExtra = false,
        array $excludeFields = array()
    ) {
        if (!extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required to use the SlackHandler');
        }

        parent::__construct('ssl://slack.com:443', $level, $bubble);

        $this->slackRecord = new SlackRecord(
            $channel,
            $username,
            $useAttachment,
            $iconEmoji,
            $useShortAttachment,
            $includeContextAndExtra,
            $excludeFields
        );

        $this->token = $token;
    }

    public function getSlackRecord(): SlackRecord
    {
        return $this->slackRecord;
    }

    public function getToken(): string
    {
        return $this->token;
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
        $dataArray = $this->prepareContentData($record);

        return http_build_query($dataArray);
    }

    protected function prepareContentData(array $record): array
    {
        $dataArray = $this->slackRecord->getSlackData($record);
        $dataArray['token'] = $this->token;

        if (!empty($dataArray['attachments'])) {
            $dataArray['attachments'] = Utils::jsonEncode($dataArray['attachments']);
        }

        return $dataArray;
    }

    /**
     * Builds the header of the API Call
     */
    private function buildHeader(string $content): string
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
     */
    protected function write(array $record): void
    {
        parent::write($record);
        $this->finalizeWrite();
    }

    /**
     * Finalizes the request by reading some bytes and then closing the socket
     *
     * If we do not read some but close the socket too early, slack sometimes
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

    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        parent::setFormatter($formatter);
        $this->slackRecord->setFormatter($formatter);

        return $this;
    }

    public function getFormatter(): FormatterInterface
    {
        $formatter = parent::getFormatter();
        $this->slackRecord->setFormatter($formatter);

        return $formatter;
    }

    /**
     * Channel used by the bot when posting
     */
    public function setChannel(string $channel): self
    {
        $this->slackRecord->setChannel($channel);

        return $this;
    }

    /**
     * Username used by the bot when posting
     */
    public function setUsername(string $username): self
    {
        $this->slackRecord->setUsername($username);

        return $this;
    }

    public function useAttachment(bool $useAttachment): self
    {
        $this->slackRecord->useAttachment($useAttachment);

        return $this;
    }

    public function setIconEmoji(string $iconEmoji): self
    {
        $this->slackRecord->setUserIcon($iconEmoji);

        return $this;
    }

    public function useShortAttachment(bool $useShortAttachment): self
    {
        $this->slackRecord->useShortAttachment($useShortAttachment);

        return $this;
    }

    public function includeContextAndExtra(bool $includeContextAndExtra): self
    {
        $this->slackRecord->includeContextAndExtra($includeContextAndExtra);

        return $this;
    }

    public function excludeFields(array $excludeFields): self
    {
        $this->slackRecord->excludeFields($excludeFields);

        return $this;
    }
}
