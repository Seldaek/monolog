<?php

declare(strict_types=1);

namespace Monolog\Formatter;

class LokiFormatter extends NormalizerFormatter
{
    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contextPrefix;

    /**
     * @var array labels that will be used for all messages (optional)
     */
    protected $labels;

    /**
     * @var array context that will be used for all messages (optional)
     */
    protected $context;

    public function __construct(
        array $labels = [],
        array $context = [],
        ?string $systemName = null,
        ?string $extraPrefix = '',
        string $contextPrefix = 'ctxt_'
    ) {
        parent::__construct();

        $this->labels = $labels;
        $this->context = $context;
        $this->labels['host'] = $systemName ?? gethostname();
        $this->extraPrefix = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
    }

    public function format(array $record): array
    {
        $customLabels = $record['context']['labels'] ?? [];
        unset($record['context']['labels']);
        $record['context'] = array_merge($this->context, $record['context']);
        $preparedRecord = $this->prepareRecord($record);
        /** @var \DateTimeInterface $datetime */
        $datetime = $preparedRecord['datetime'];

        return [
            'stream' => array_merge($this->labels, $customLabels, $this->getMonologLabels($preparedRecord)),
            'values' => [
                [
                    (string) ($datetime->getTimestamp() * 1000000000),
                    $this->toJson($this->normalize($preparedRecord)),
                ],
            ],
        ];
    }

    public function prepareRecord(array $record): array
    {
        if (!isset($record['datetime'], $record['message'], $record['level_name'])) {
            $exceptionMessage = sprintf(
                'The record should at least contain datetime, message and level_name keys, %s given',
                var_export($record, true)
            );

            throw new \InvalidArgumentException($exceptionMessage);
        }

        $preparedRecord = $record;
        if (isset($record['context'])) {
            $preparedRecord = array_merge(
                $preparedRecord,
                $this->prepareRecordList($record['context'], $this->contextPrefix)
            );
            unset($preparedRecord['context']);
        }

        if (isset($record['extra'])) {
            $preparedRecord = array_merge(
                $preparedRecord,
                $this->prepareRecordList($record['extra'], $this->extraPrefix, ['line', 'file'])
            );
            unset($preparedRecord['extra']);
        }

        if (
            !isset($preparedRecord['file'])
            && isset($preparedRecord[$this->contextPrefix . 'exception']['file'])
            && preg_match("/^(.+):([\d]+)$/", $preparedRecord[$this->contextPrefix . 'exception']['file'], $matches)
        ) {
            $preparedRecord['file'] = (string) $matches[1];
            $preparedRecord['line'] = (string) $matches[2];
        }

        return $preparedRecord;
    }

    private function prepareRecordList(array $list = [], string $prefixKey = '', array $fieldNotPrefixed = []): array
    {
        $formattedList = parent::format($list);

        foreach ($formattedList as $label => $value) {
            $key = (in_array($label, $fieldNotPrefixed, true)) ? $label : $prefixKey . $label;
            $finalValue = $value;

            $formattedList[$key] = (null !== $finalValue && !is_scalar($finalValue))
                ? $this->toJson($finalValue)
                : (string) $finalValue;

            if ($key !== $label) {
                unset($formattedList[$label]);
            }
        }

        return $formattedList;
    }

    private function getMonologLabels(array $record): array
    {
        $keepAsLabels = ['level_name', 'channel'];

        return array_filter(
            $record,
            function ($key) use ($keepAsLabels)
            {
                return in_array($key, $keepAsLabels, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }
}
