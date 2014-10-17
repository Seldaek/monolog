<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

/**
 * Encodes whatever record data is passed to it as json
 *
 * This can be useful to log to databases or remote APIs
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class JsonFormatter implements FormatterInterface
{
    const BATCH_MODE_JSON = 1;
    const BATCH_MODE_NEWLINES = 2;

    protected $batchMode;
    protected $appendNewline;

    /**
     * @param int $batchMode
     */
    public function __construct($batchMode = self::BATCH_MODE_JSON, $appendNewline = true)
    {
        $this->batchMode = $batchMode;
        $this->appendNewline = $appendNewline;
    }

    /**
     * The batch mode option configures the formatting style for
     * multiple records. By default, multiple records will be
     * formatted as a JSON-encoded array. However, for
     * compatibility with some API endpoints, alternive styles
     * are available.
     *
     * @return int
     */
    public function getBatchMode()
    {
        return $this->batchMode;
    }

    /**
     * True if newlines are appended to every formatted record
     *
     * @return bool
     */
    public function isAppendingNewlines()
    {
        return $this->appendNewline;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        return json_encode($record) . ($this->appendNewline ? "\n" : '');
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        switch ($this->batchMode) {
            case static::BATCH_MODE_NEWLINES:
                return $this->formatBatchNewlines($records);

            case static::BATCH_MODE_JSON:
            default:
                return $this->formatBatchJson($records);
        }
    }

    /**
     * Return a JSON-encoded array of records.
     *
     * @param  array  $records
     * @return string
     */
    protected function formatBatchJson(array $records)
    {
        return json_encode($records);
    }

    /**
     * Use new lines to separate records instead of a
     * JSON-encoded array.
     *
     * @param  array  $records
     * @return string
     */
    protected function formatBatchNewlines(array $records)
    {
        $instance = $this;

        $oldNewline = $this->appendNewline;
        $this->appendNewline = false;
        array_walk($records, function (&$value, $key) use ($instance) {
            $value = $instance->format($value);
        });
        $this->appendNewline = $oldNewline;

        return implode("\n", $records);
    }
}
