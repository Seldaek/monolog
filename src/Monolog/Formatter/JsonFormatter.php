<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Utils;
use Throwable;

/**
 * Encodes whatever record data is passed to it as json
 *
 * This can be useful to log to databases or remote APIs
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class JsonFormatter extends NormalizerFormatter
{
    public const BATCH_MODE_JSON = 1;
    public const BATCH_MODE_NEWLINES = 2;

    protected $batchMode;
    protected $appendNewline;

    /**
     * @var bool
     */
    protected $includeStacktraces = false;

    public function __construct(int $batchMode = self::BATCH_MODE_JSON, bool $appendNewline = true)
    {
        $this->batchMode = $batchMode;
        $this->appendNewline = $appendNewline;
    }

    /**
     * The batch mode option configures the formatting style for
     * multiple records. By default, multiple records will be
     * formatted as a JSON-encoded array. However, for
     * compatibility with some API endpoints, alternative styles
     * are available.
     */
    public function getBatchMode(): int
    {
        return $this->batchMode;
    }

    /**
     * True if newlines are appended to every formatted record
     */
    public function isAppendingNewlines(): bool
    {
        return $this->appendNewline;
    }

    /**
     * {@inheritdoc}
     *
     * @suppress PhanTypeComparisonToArray
     */
    public function format(array $record): string
    {
        $normalized = $this->normalize($record);
        if (isset($normalized['context']) && $normalized['context'] === []) {
            $normalized['context'] = new \stdClass;
        }
        if (isset($normalized['extra']) && $normalized['extra'] === []) {
            $normalized['extra'] = new \stdClass;
        }

        return $this->toJson($normalized, true) . ($this->appendNewline ? "\n" : '');
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records): string
    {
        switch ($this->batchMode) {
            case static::BATCH_MODE_NEWLINES:
                return $this->formatBatchNewlines($records);

            case static::BATCH_MODE_JSON:
            default:
                return $this->formatBatchJson($records);
        }
    }

    public function includeStacktraces(bool $include = true)
    {
        $this->includeStacktraces = $include;
    }

    /**
     * Return a JSON-encoded array of records.
     */
    protected function formatBatchJson(array $records): string
    {
        return $this->toJson($this->normalize($records), true);
    }

    /**
     * Use new lines to separate records instead of a
     * JSON-encoded array.
     */
    protected function formatBatchNewlines(array $records): string
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

    /**
     * Normalizes given $data.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    protected function normalize($data, int $depth = 0)
    {
        if ($depth > $this->maxNormalizeDepth) {
            return 'Over '.$this->maxNormalizeDepth.' levels deep, aborting normalization';
        }

        if (is_array($data)) {
            $normalized = [];

            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > $this->maxNormalizeItemCount) {
                    $normalized['...'] = 'Over '.$this->maxNormalizeItemCount.' items ('.count($data).' total), aborting normalization';
                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if ($data instanceof Throwable) {
            return $this->normalizeException($data, $depth);
        }

        if (is_resource($data)) {
            return parent::normalize($data);
        }

        return $data;
    }

    /**
     * Normalizes given exception with or without its own stack trace based on
     * `includeStacktraces` property.
     */
    protected function normalizeException(Throwable $e, int $depth = 0): array
    {
        $data = parent::normalizeException($e, $depth);
        if (!$this->includeStacktraces) {
            unset($data['trace']);
        }

        return $data;
    }
}
