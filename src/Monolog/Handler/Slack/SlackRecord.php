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

use Monolog\Logger;
use Monolog\Utils;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Formatter\FormatterInterface;

/**
 * Slack record utility helping to log to Slack webhooks or API.
 *
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 * @see    https://api.slack.com/docs/message-attachments
 *
 * @phpstan-import-type FormattedRecord from \Monolog\Handler\AbstractProcessingHandler
 * @phpstan-import-type Record from \Monolog\Logger
 */
class SlackRecord
{
    public const COLOR_DANGER = 'danger';

    public const COLOR_WARNING = 'warning';

    public const COLOR_GOOD = 'good';

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

    /**
     * Whether the message should be added to Slack as attachment (plain text otherwise)
     * @var bool
     */
    private $useAttachment;

    /**
     * Whether the the context/extra messages added to Slack as attachments are in a short style
     * @var bool
     */
    private $useShortAttachment;

    /**
     * Whether the attachment should include context and extra data
     * @var bool
     */
    private $includeContextAndExtra;

    /**
     * Dot separated list of fields to exclude from slack message. E.g. ['context.field1', 'extra.field2']
     * @var string[]
     */
    private $excludeFields;

    /**
     * @var ?FormatterInterface
     */
    private $formatter;

    /**
     * @var NormalizerFormatter
     */
    private $normalizerFormatter;

    /**
     * @param string[] $excludeFields
     */
    public function __construct(
        ?string $channel = null,
        ?string $username = null,
        bool $useAttachment = true,
        ?string $userIcon = null,
        bool $useShortAttachment = false,
        bool $includeContextAndExtra = false,
        array $excludeFields = array(),
        FormatterInterface $formatter = null
    ) {
        $this
            ->setChannel($channel)
            ->setUsername($username)
            ->useAttachment($useAttachment)
            ->setUserIcon($userIcon)
            ->useShortAttachment($useShortAttachment)
            ->includeContextAndExtra($includeContextAndExtra)
            ->excludeFields($excludeFields)
            ->setFormatter($formatter);

        if ($this->includeContextAndExtra) {
            $this->normalizerFormatter = new NormalizerFormatter();
        }
    }

    /**
     * Returns required data in format that Slack
     * is expecting.
     *
     * @phpstan-param FormattedRecord $record
     * @phpstan-return mixed[]
     */
    public function getSlackData(array $record): array
    {
        $dataArray = array();
        $record = $this->removeExcludedFields($record);

        if ($this->username) {
            $dataArray['username'] = $this->username;
        }

        if ($this->channel) {
            $dataArray['channel'] = $this->channel;
        }

        if ($this->formatter && !$this->useAttachment) {
            /** @phpstan-ignore-next-line */
            $message = $this->formatter->format($record);
        } else {
            $message = $record['message'];
        }

        if ($this->useAttachment) {
            $attachment = array(
                'fallback'    => $message,
                'text'        => $message,
                'color'       => $this->getAttachmentColor($record['level']),
                'fields'      => array(),
                'mrkdwn_in'   => array('fields'),
                'ts'          => $record['datetime']->getTimestamp(),
                'footer'      => $this->username,
                'footer_icon' => $this->userIcon,
            );

            if ($this->useShortAttachment) {
                $attachment['title'] = $record['level_name'];
            } else {
                $attachment['title'] = 'Message';
                $attachment['fields'][] = $this->generateAttachmentField('Level', $record['level_name']);
            }

            if ($this->includeContextAndExtra) {
                foreach (array('extra', 'context') as $key) {
                    if (empty($record[$key])) {
                        continue;
                    }

                    if ($this->useShortAttachment) {
                        $attachment['fields'][] = $this->generateAttachmentField(
                            (string) $key,
                            $record[$key]
                        );
                    } else {
                        // Add all extra fields as individual fields in attachment
                        $attachment['fields'] = array_merge(
                            $attachment['fields'],
                            $this->generateAttachmentFields($record[$key])
                        );
                    }
                }
            }

            $dataArray['attachments'] = array($attachment);
        } else {
            $dataArray['text'] = $message;
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
     * Returns a Slack message attachment color associated with
     * provided level.
     */
    public function getAttachmentColor(int $level): string
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return static::COLOR_DANGER;
            case $level >= Logger::WARNING:
                return static::COLOR_WARNING;
            case $level >= Logger::INFO:
                return static::COLOR_GOOD;
            default:
                return static::COLOR_DEFAULT;
        }
    }

    /**
     * Stringifies an array of key/value pairs to be used in attachment fields
     *
     * @param mixed[] $fields
     */
    public function stringify(array $fields): string
    {
        /** @var Record $fields */
        $normalized = $this->normalizerFormatter->format($fields);

        $hasSecondDimension = count(array_filter($normalized, 'is_array'));
        $hasNonNumericKeys = !count(array_filter(array_keys($normalized), 'is_numeric'));

        return $hasSecondDimension || $hasNonNumericKeys
            ? Utils::jsonEncode($normalized, JSON_PRETTY_PRINT|Utils::DEFAULT_JSON_FLAGS)
            : Utils::jsonEncode($normalized, Utils::DEFAULT_JSON_FLAGS);
    }

    /**
     * Channel used by the bot when posting
     *
     * @param ?string $channel
     *
     * @return static
     */
    public function setChannel(?string $channel = null): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Username used by the bot when posting
     *
     * @param ?string $username
     *
     * @return static
     */
    public function setUsername(?string $username = null): self
    {
        $this->username = $username;

        return $this;
    }

    public function useAttachment(bool $useAttachment = true): self
    {
        $this->useAttachment = $useAttachment;

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

    public function useShortAttachment(bool $useShortAttachment = false): self
    {
        $this->useShortAttachment = $useShortAttachment;

        return $this;
    }

    public function includeContextAndExtra(bool $includeContextAndExtra = false): self
    {
        $this->includeContextAndExtra = $includeContextAndExtra;

        if ($this->includeContextAndExtra) {
            $this->normalizerFormatter = new NormalizerFormatter();
        }

        return $this;
    }

    /**
     * @param string[] $excludeFields
     */
    public function excludeFields(array $excludeFields = []): self
    {
        $this->excludeFields = $excludeFields;

        return $this;
    }

    public function setFormatter(?FormatterInterface $formatter = null): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * Generates attachment field
     *
     * @param string|mixed[] $value
     *
     * @return array{title: string, value: string, short: false}
     */
    private function generateAttachmentField(string $title, $value): array
    {
        $value = is_array($value)
            ? sprintf('```%s```', substr($this->stringify($value), 0, 1990))
            : $value;

        return array(
            'title' => ucfirst($title),
            'value' => $value,
            'short' => false,
        );
    }

    /**
     * Generates a collection of attachment fields from array
     *
     * @param mixed[] $data
     *
     * @return array<array{title: string, value: string, short: false}>
     */
    private function generateAttachmentFields(array $data): array
    {
        /** @var Record $data */
        $normalized = $this->normalizerFormatter->format($data);

        $fields = array();
        foreach ($normalized as $key => $value) {
            $fields[] = $this->generateAttachmentField((string) $key, $value);
        }

        return $fields;
    }

    /**
     * Get a copy of record with fields excluded according to $this->excludeFields
     *
     * @phpstan-param FormattedRecord $record
     *
     * @return mixed[]
     */
    private function removeExcludedFields(array $record): array
    {
        foreach ($this->excludeFields as $field) {
            $keys = explode('.', $field);
            $node = &$record;
            $lastKey = end($keys);
            foreach ($keys as $key) {
                if (!isset($node[$key])) {
                    break;
                }
                if ($lastKey === $key) {
                    unset($node[$key]);
                    break;
                }
                $node = &$node[$key];
            }
        }

        return $record;
    }
}
