<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Teams;

use Monolog\Level;
use Monolog\Utils;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * MS Teams record utility helping to log to MS Teams webhooks.
 *
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 * @see    https://learn.microsoft.com/adaptive-cards/authoring-cards/getting-started
 */
class TeamsRecord
{
    public const COLOR_ATTENTION = 'attention';

    public const COLOR_WARNING = 'warning';

    public const COLOR_GOOD = 'good';

    public const COLOR_DEFAULT = 'default';

    /**
     * Whether the card should include context and extra data
     */
    private bool $includeContextAndExtra;

    /**
     * Dot separated list of fields to exclude from MS Teams message. E.g. ['context.field1', 'extra.field2']
     * @var string[]
     */
    private array $excludeFields;

    /**
     * Dot separated list of fields to display with a toggle button in MS Teams message. E.g. ['context.field1', 'extra.field2']
     * @var string[]
     */
    private array $toggleFields;

    private FormatterInterface|null $formatter;

    private NormalizerFormatter $normalizerFormatter;

    /**
     * @param string[] $excludeFields
     * @param string[] $toggleFields
     */
    public function __construct(
        bool $includeContextAndExtra = false,
        array $excludeFields = [],
        array $toggleFields = [],
        FormatterInterface|null $formatter = null
    ) {
        $this
            ->includeContextAndExtra($includeContextAndExtra)
            ->excludeFields($excludeFields)
            ->toggleFields($toggleFields)
            ->setFormatter($formatter);
    }

    /**
     * Returns required data in format that MS Teams is expecting.
     *
     * @phpstan-return mixed[]
     */
    public function getAdaptiveCardPayload(LogRecord $record): array
    {
        if ($this->formatter !== null) {
            $message = $this->formatter->format($record);
        } else {
            $message = $record->message;
        }

        $recordData = $this->removeExcludedFields($record);

        $facts = $toggles = [];

        $facts[] = $this->generateFactField('Level', $recordData['level_name']);

        if ($this->includeContextAndExtra) {
            foreach (['extra', 'context'] as $key) {
                if (!isset($recordData[$key]) || \count($recordData[$key]) === 0) {
                    continue;
                }

                $data = $this->generateContextAndExtraFields($recordData[$key], $key);

                $facts = array_merge($facts, $data['facts']);
                $toggles = array_merge($toggles, $data['toggles']);
            }
        }

        return [
            'type'        => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content'     => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type'    => 'AdaptiveCard',
                        'version' => '1.5',
                        'body'    => [
                            // Card Header
                            [
                                'type'  => 'Container',
                                'style' => $this->getContainerStyle($record->level),
                                'items' => [
                                    [
                                        'type'   => 'TextBlock',
                                        'text'   => $message,
                                        'weight' => 'Bolder',
                                        'size'   => 'Medium',
                                        'wrap'   => true,
                                    ],
                                ],
                            ],
                            // Context and Extra
                            [
                                'type'    => 'Container',
                                'spacing' => 'Medium',
                                'items'   => [
                                    [
                                        'type'  => 'FactSet',
                                        'facts' => $facts,
                                    ],
                                ],
                            ]
                        ],
                        // Toggles
                        'actions' => $toggles,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns MS Teams container style associated with provided level.
     */
    public function getContainerStyle(Level $level): string
    {
        return match ($level) {
            Level::Error, Level::Critical, Level::Alert, Level::Emergency => static::COLOR_ATTENTION,
            Level::Warning => static::COLOR_WARNING,
            Level::Info, Level::Notice => static::COLOR_GOOD,
            Level::Debug => static::COLOR_DEFAULT
        };
    }

    /**
     * Stringifies an array of key/value pairs to be used in fact fields
     *
     * @param mixed[] $fields
     */
    public function stringify(array $fields): string
    {
        /** @var array<array<mixed>|bool|float|int|string|null> $normalized */
        $normalized = $this->normalizerFormatter->normalizeValue($fields);

        $hasSecondDimension = \count(array_filter($normalized, 'is_array')) > 0;
        $hasOnlyNonNumericKeys = \count(array_filter(array_keys($normalized), 'is_numeric')) === 0;

        return $hasSecondDimension || $hasOnlyNonNumericKeys
            ? Utils::jsonEncode($normalized, JSON_PRETTY_PRINT|Utils::DEFAULT_JSON_FLAGS)
            : Utils::jsonEncode($normalized, Utils::DEFAULT_JSON_FLAGS);
    }

    /**
     * @return $this
     */
    public function includeContextAndExtra(bool $includeContextAndExtra = false): self
    {
        $this->includeContextAndExtra = $includeContextAndExtra;

        if ($this->includeContextAndExtra) {
            $this->normalizerFormatter = new NormalizerFormatter();
        }

        return $this;
    }

    /**
     * @param  string[] $excludeFields
     * @return $this
     */
    public function excludeFields(array $excludeFields = []): self
    {
        $this->excludeFields = $excludeFields;

        return $this;
    }

    /**
     * @param  string[] $toggleFields
     * @return $this
     */
    public function toggleFields(array $toggleFields = []): self
    {
        $this->toggleFields = $toggleFields;

        return $this;
    }

    /**
     * @return $this
     */
    public function setFormatter(?FormatterInterface $formatter = null): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * Generates fact field
     *
     * @param string|mixed[] $value
     *
     * @return array{title: string, value: string}
     */
    private function generateFactField(string $title, $value): array
    {
        $value = \is_array($value)
            ? substr($this->stringify($value), 0, 1990)
            : $value;

        return [
            'title' => ucfirst($title),
            'value' => $value,
        ];
    }

    /**
     * Generates fact field
     *
     * @param string|mixed[] $value
     *
     * @return array{type: string, title: string, card: array{type: string, body: array<array{type: string, text: string, wrap: bool}>}}
     */
    private function generateToggleField(string $title, $value): array
    {
        $value = \is_array($value)
            ? substr($this->stringify($value), 0, 19990)
            : $value;

        return [
            'type'  => 'Action.ShowCard',
            'title' => ucfirst($title),
            'card'  => [
                'type' => 'AdaptiveCard',
                'body' => [
                    [
                        'type' => 'TextBlock',
                        'text' => $value,
                        'wrap' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Generates a collection of fact fields from array
     *
     * @param mixed[] $data
     *
     * @return array{facts: array<array{title: string, value: string}>, toggles: array<array{type: string, title: string, card: array{type: string, body: array<array{type: string, text: string, wrap: bool}>}}>}
     */
    private function generateContextAndExtraFields(array $data, string $type): array
    {
        /** @var array<array<mixed>|string> $normalized */
        $normalized = $this->normalizerFormatter->normalizeValue($data);

        $fields = [
            'facts' => [],
            'toggles' => [],
        ];

        foreach ($normalized as $key => $value) {
            if (in_array($type.'.'.$key, $this->toggleFields, true)) {
                $fields['toggles'][] = $this->generateToggleField((string) $key, $value);
            } else {
                $fields['facts'][] = $this->generateFactField((string) $key, $value);
            }
        }

        return $fields;
    }

    /**
     * Get a copy of record with fields excluded according to $this->excludeFields
     *
     * @return mixed[]
     */
    private function removeExcludedFields(LogRecord $record): array
    {
        $recordData = $record->toArray();
        foreach ($this->excludeFields as $field) {
            $keys = explode('.', $field);
            $node = &$recordData;
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

        return $recordData;
    }
}
