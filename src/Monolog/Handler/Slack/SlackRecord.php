<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Slack;

use InvalidArgumentException;
use Monolog\Formatter\SlackFormatter;

/**
 * Slack record utility helping to log to Slack webhooks or API.
 *
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 * @see    https://api.slack.com/docs/message-attachments
 */
class SlackRecord
{
    /** @deprecated Use {@see SlackFormatter::COLOR_DANGER} */
    public const COLOR_DANGER = 'danger';

    /** @deprecated Use {@see SlackFormatter::COLOR_WARNING} */
    public const COLOR_WARNING = 'warning';

    /** @deprecated Use {@see SlackFormatter::COLOR_GOOD} */
    public const COLOR_GOOD = 'good';

    /** @deprecated Use {@see SlackFormatter::COLOR_DEFAULT} */
    public const COLOR_DEFAULT = '#e3e4e6';

    /**
     * Slack channel (encoded ID or name)
     * @var string|null
     */
    private $channel;

    /**
     * Name of a bot
     * @var string|null
     */
    private $username;

    /**
     * User icon e.g. 'ghost', 'http://example.com/user.png'
     * @var string|null
     */
    private $userIcon;

    public function __construct(
        ?string $channel = null,
        ?string $username = null,
        ?string $userIcon = null
    ) {
        $this
            ->setChannel($channel)
            ->setUsername($username)
            ->setUserIcon($userIcon);
    }

    /**
     * Returns required data in format that Slack
     * is expecting.
     */
    public function getSlackData(array $record): array
    {
        $dataArray = array();

        if ($this->username) {
            $dataArray['username'] = $this->username;
        }

        if ($this->channel) {
            $dataArray['channel'] = $this->channel;
        }

        // Add the slack-formatted message to the json body
        $message = $record['formatted'] ?? $record['message'];

        if (is_array($message)) {
            $dataArray = array_merge($dataArray, $message);
        } else if (is_scalar($message)) {
            $dataArray['text'] = (string) $message;
        } else {
            throw new InvalidArgumentException(
                'Expected formatter to return a scalar or a slack message array. Instead got type '
                . gettype($message)
            );
        }

        if ($this->userIcon) {
            if (filter_var($this->userIcon, FILTER_VALIDATE_URL)) {
                $dataArray['icon_url'] = $this->userIcon;
            } else {
                $dataArray['icon_emoji'] = ":{$this->userIcon}:";
            }
        }

        return $dataArray;
    }

    /**
     * Channel used by the bot when posting
     */
    public function setChannel(?string $channel = null): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Username used by the bot when posting
     */
    public function setUsername(?string $username = null): self
    {
        $this->username = $username;

        return $this;
    }

    public function setUserIcon(?string $userIcon = null): self
    {
        $this->userIcon = $userIcon;

        if (\is_string($userIcon)) {
            $this->userIcon = trim($userIcon, ':');
        }

        return $this;
    }
}
