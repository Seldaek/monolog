<?php

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
use Monolog\Formatter\LineFormatter;
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
    const COLOR_DANGER = 'danger';

    const COLOR_WARNING = 'warning';

    const COLOR_GOOD = 'good';

    const COLOR_DEFAULT = '#e3e4e6';

    /**
     * Slack channel (encoded ID or name)
     * @var string|null
     */
    private $channel;

    /**
     * Name of a bot
     * @var string
     */
    private $username;

    /**
     * Emoji icon name
     * @var string
     */
    private $iconEmoji;

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
     * @var FormatterInterface
     */
    private $formatter;

    /**
     * @var LineFormatter
     */
    private $lineFormatter;

    public function __construct($channel = null, $username = 'Monolog', $useAttachment = true, $iconEmoji = null, $useShortAttachment = false, $includeContextAndExtra = false, FormatterInterface $formatter = null)
    {
        $this->channel = $channel;
        $this->username = $username;
        $this->iconEmoji = trim($iconEmoji, ':');
        $this->useAttachment = $useAttachment;
        $this->useShortAttachment = $useShortAttachment;
        $this->includeContextAndExtra = $includeContextAndExtra;
        $this->formatter = $formatter;

        if ($this->includeContextAndExtra) {
            $this->lineFormatter = new LineFormatter();
        }
    }

    public function getSlackData(array $record)
    {
        $dataArray = array(
            'username'    => $this->username,
            'text'        => '',
        );

        if ($this->channel) {
            $dataArray['channel'] = $this->channel;
        }

        if ($this->formatter) {
            $message = $this->formatter->format($record);
        } else {
            $message = $record['message'];
        }

        if ($this->useAttachment) {
            $attachment = array(
                'fallback' => $message,
                'text'     => $message,
                'color'    => $this->getAttachmentColor($record['level']),
                'fields'   => array(),
            );

            if ($this->useShortAttachment) {
                $attachment['title'] = $record['level_name'];
            } else {
                $attachment['title'] = 'Message';
                $attachment['fields'][] = $this->generateAttachmentField('Level', $record['level_name'], true);
            }

            if ($this->includeContextAndExtra) {
                foreach (array('extra', 'context') as $key) {
                    if (empty($record[$key])) {
                        continue;
                    }

                    if ($this->useShortAttachment) {
                        $attachment['fields'][] = $this->generateAttachmentField(
                            ucfirst($key),
                            $this->stringify($record[$key]),
                            true
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

        if ($this->iconEmoji) {
            $dataArray['icon_emoji'] = ":{$this->iconEmoji}:";
        }

        return $dataArray;
    }

    /**
     * Returned a Slack message attachment color associated with
     * provided level.
     *
     * @param  int    $level
     * @return string
     */
    public function getAttachmentColor($level)
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return self::COLOR_DANGER;
            case $level >= Logger::WARNING:
                return self::COLOR_WARNING;
            case $level >= Logger::INFO:
                return self::COLOR_GOOD;
            default:
                return self::COLOR_DEFAULT;
        }
    }

    /**
     * Stringifies an array of key/value pairs to be used in attachment fields
     *
     * @param  array  $fields
     * @return string|null
     */
    public function stringify($fields)
    {
        if (!$this->lineFormatter) {
            return null;
        }

        $string = '';
        foreach ($fields as $var => $val) {
            $string .= $var.': '.$this->lineFormatter->stringify($val)." | ";
        }

        $string = rtrim($string, " |");

        return $string;
    }

    /**
     * Sets the formatter
     *
     * @param FormatterInterface $formatter
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Generates attachment field
     *
     * @param string $title
     * @param string|array $value
     * @param bool $short
     * @return array
     */
    private function generateAttachmentField($title, $value, $short)
    {
        return array(
            'title' => $title,
            'value' => is_array($value) ? $this->lineFormatter->stringify($value) : $value,
            'short' => $short
        );
    }

    /**
     * Generates a collection of attachment fields from array
     *
     * @param array $data
     * @return array
     */
    private function generateAttachmentFields(array $data)
    {
        $fields = array();
        foreach ($data as $key => $value) {
            $fields[] = $this->generateAttachmentField($key, $value, false);
        }

        return $fields;
    }
}
