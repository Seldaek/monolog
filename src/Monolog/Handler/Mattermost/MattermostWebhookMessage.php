<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Mattermost;

/**
 * Mattermost webhook message.
 *
 * @author Christin Gruber <c.gruber@touchdesign.de>
 */
class MattermostWebhookMessage
{
    private $iconUrl;
    private $username;
    private $channel;
    private $text;

    public function __toString(): string
    {
        $message = [];
        if ($this->iconUrl) {
            $message['icon_url'] = $this->iconUrl;
        }
        if ($this->username) {
            $message['username'] = $this->username;
        }
        if ($this->channel) {
            $message['channel'] = $this->channel;
        }
        if ($this->text) {
            $message['text'] = $this->text;
        }

        return json_encode($message);
    }

    public function setIconUrl(?string $iconUrl): self
    {
        $this->iconUrl = $iconUrl;

        return $this;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function setChannel(?string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
