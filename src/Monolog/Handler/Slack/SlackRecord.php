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
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Formatter\FormatterInterface;

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
    public const COLOR_DANGER = 'danger';

    public const COLOR_WARNING = 'warning';

    public const COLOR_GOOD = 'good';

    public const COLOR_DEFAULT = '#e3e4e6';

    /**
     * Slack channel (encoded ID or name)
     * @var ?string
     */
    private $channel;

    /**
     * Name of a bot
     * @var ?string
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
     * @var array
     */
    private $excludeFields;

    /**
     * @var FormatterInterface
     */
    private $formatter;

    /**
     * @var NormalizerFormatter
     */
    private $normalizerFormatter;

    public function __construct(?string $channel = null, ?string $username = null, bool $useAttachment = true, ?string $userIcon = null, bool $useShortAttachment = false, bool $includeContextAndExtra = false, array $excludeFields = array(), FormatterInterface $formatter = null)
    {
        $this->channel = $channel;
        $this->username = $username;
        $this->userIcon = $userIcon !== null ? trim($userIcon, ':') : null;
        $this->useAttachment = $useAttachment;
        $this->useShortAttachment = $useShortAttachment;
        $this->includeContextAndExtra = $includeContextAndExtra;
        $this->excludeFields = $excludeFields;
        $this->formatter = $formatter;

        if ($this->includeContextAndExtra) {
            $this->normalizerFormatter = new NormalizerFormatter();
        }
    }

    public function getSlackData(array $record): array
    {
        $dataArray = array();
        $record = $this->excludeFields($record);

        if ($this->username) {
            $dataArray['username'] = $this->username;
        }

        if ($this->channel) {
            $dataArray['channel'] = $this->channel;
        }

        if ($this->formatter && !$this->useAttachment) {
            $message = $this->formatter->format($record);
        } else {
            $message = $record['message'];
        }

        if ($this->useAttachment) {
            $attachment = array(
                'fallback'  => $message,
                'text'      => $message,
                'color'     => $this->getAttachmentColor($record['level']),
                'fields'    => array(),
                'mrkdwn_in' => array('fields'),
                'ts'        => $record['datetime']->getTimestamp(),
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
                            $key,
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
     * Returned a Slack message attachment color associated with
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
     */
    public function stringify(array $fields): string
    {
        $normalized = $this->normalizerFormatter->format($fields);
        $prettyPrintFlag = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 128;

        $hasSecondDimension = count(array_filter($normalized, 'is_array'));
        $hasNonNumericKeys = !count(array_filter(array_keys($normalized), 'is_numeric'));

        return $hasSecondDimension || $hasNonNumericKeys
            ? json_encode($normalized, $prettyPrintFlag|JSON_UNESCAPED_UNICODE)
            : json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    public function setFormatter(FormatterInterface $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * Generates attachment field
     *
     * @param string|array $value
     */
    private function generateAttachmentField(string $title, $value): array
    {
        $value = is_array($value)
            ? sprintf('```%s```', $this->stringify($value))
            : $value;

        return array(
            'title' => ucfirst($title),
            'value' => $value,
            'short' => false,
        );
    }

    /**
     * Generates a collection of attachment fields from array
     */
    private function generateAttachmentFields(array $data): array
    {
        $fields = array();
        foreach ($this->normalizerFormatter->format($data) as $key => $value) {
            $fields[] = $this->generateAttachmentField($key, $value);
        }

        return $fields;
    }

    /**
     * Get a copy of record with fields excluded according to $this->excludeFields
     */
    private function excludeFields(array $record): array
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
