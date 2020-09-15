<?php


namespace Monolog\Formatter;


use Monolog\Logger;
use Monolog\Utils;

/**
 * Formats the message as a Slack message with the schema {@link https://api.slack.com/messaging/composing/layouts}
 *
 * @author Tim Finucane <timfinucane@outlook.com>
 */
class SlackFormatter extends NormalizerFormatter
{
    public const COLOR_DANGER = 'danger';

    public const COLOR_WARNING = 'warning';

    public const COLOR_GOOD = 'good';

    public const COLOR_DEFAULT = '#e3e4e6';

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

    public function __construct(
        bool $useAttachment = true,
        bool $useShortAttachment = false,
        bool $includeContextAndExtra = false,
        array $excludeFields = array(),
        ?string $dateFormat = null
    ) {
        parent::__construct($dateFormat);

        $this
            ->useAttachment($useAttachment)
            ->useShortAttachment($useShortAttachment)
            ->includeContextAndExtra($includeContextAndExtra)
            ->excludeFields($excludeFields);
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = $this->removeExcludedFields($record);

        // The final array being output. Should be a partial of https://api.slack.com/messaging/composing/layouts
        $dataArray = array();

        $message = $record['message'];

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
                foreach (['extra', 'context'] as $key) {
                    if (empty($record[$key])) {
                        continue;
                    }

                    if ($this->useShortAttachment) {
                        $attachment['fields'][] = $this->generateAttachmentField(
                            (string)$key,
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

        return $dataArray;
    }

    /**
     * @param bool $useAttachment
     *
     * @return SlackFormatter
     */
    public function useAttachment(bool $useAttachment): SlackFormatter
    {
        $this->useAttachment = $useAttachment;
        return $this;
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
     * @param bool $useShortAttachment
     *
     * @return SlackFormatter
     */
    public function useShortAttachment(bool $useShortAttachment): SlackFormatter
    {
        $this->useShortAttachment = $useShortAttachment;
        return $this;
    }

    /**
     * @param bool $includeContextAndExtra
     *
     * @return SlackFormatter
     */
    public function includeContextAndExtra(bool $includeContextAndExtra): SlackFormatter
    {
        $this->includeContextAndExtra = $includeContextAndExtra;
        return $this;
    }

    /**
     * @param array $excludeFields
     *
     * @return SlackFormatter
     */
    public function excludeFields(array $excludeFields): SlackFormatter
    {
        $this->excludeFields = $excludeFields;
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
     */
    private function generateAttachmentFields(array $data): array
    {
        $fields = array();
        foreach ($this->normalize($data) as $key => $value) {
            $fields[] = $this->generateAttachmentField((string) $key, $value);
        }

        return $fields;
    }

    /**
     * Stringifies an array of key/value pairs to be used in attachment fields
     */
    public function stringify(array $fields): string
    {
        $normalized = $this->normalize($fields);

        $hasSecondDimension = count(array_filter($normalized, 'is_array'));
        $hasNonNumericKeys = !count(array_filter(array_keys($normalized), 'is_numeric'));

        return $hasSecondDimension || $hasNonNumericKeys
            ? Utils::jsonEncode($normalized, JSON_PRETTY_PRINT|Utils::DEFAULT_JSON_FLAGS)
            : Utils::jsonEncode($normalized, Utils::DEFAULT_JSON_FLAGS);
    }

    /**
     * Get a copy of record with fields excluded according to $this->excludeFields
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
