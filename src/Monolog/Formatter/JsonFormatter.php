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

use Throwable;
use Monolog\LogRecord;

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

    /** @var self::BATCH_MODE_* */
    protected int $batchMode;

    protected bool $appendNewline;

    protected bool $ignoreEmptyContextAndExtra;

    protected bool $includeStacktraces = false;

    /**
     * @param self::BATCH_MODE_* $batchMode
     */
    public function __construct(int $batchMode = self::BATCH_MODE_JSON, bool $appendNewline = true, bool $ignoreEmptyContextAndExtra = false, bool $includeStacktraces = false)
    {
        $this->batchMode = $batchMode;
        $this->appendNewline = $appendNewline;
        $this->ignoreEmptyContextAndExtra = $ignoreEmptyContextAndExtra;
        $this->includeStacktraces = $includeStacktraces;

        parent::__construct();
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
     * @inheritDoc
     */
    public function format(LogRecord $record): string
    {
        $normalized = parent::format($record);

        if (isset($normalized['context']) && $normalized['context'] === []) {
            if ($this->ignoreEmptyContextAndExtra) {
                unset($normalized['context']);
            } else {
                $normalized['context'] = new \stdClass;
            }
        }
        if (isset($normalized['extra']) && $normalized['extra'] === []) {
            if ($this->ignoreEmptyContextAndExtra) {
                unset($normalized['extra']);
            } else {
                $normalized['extra'] = new \stdClass;
            }
        }

        return $this->toJson($normalized, true) . ($this->appendNewline ? "\n" : '');
    }

    /**
     * @inheritDoc
     */
    public function formatBatch(array $records): string
    {
        return match ($this->batchMode) {
            static::BATCH_MODE_NEWLINES => $this->formatBatchNewlines($records),
            default => $this->formatBatchJson($records),
        };
    }

    public function includeStacktraces(bool $include = true): self
    {
        $this->includeStacktraces = $include;

        return $this;
    }

    /**
     * Return a JSON-encoded array of records.
     *
     * @phpstan-param LogRecord[] $records
     */
    protected function formatBatchJson(array $records): string
    {
        return $this->toJson($this->normalize($records), true);
    }

    /**
     * Use new lines to separate records instead of a
     * JSON-encoded array.
     *
     * @phpstan-param LogRecord[] $records
     */
    protected function formatBatchNewlines(array $records): string
    {
        $oldNewline = $this->appendNewline;
        $this->appendNewline = false;
        $formatted = array_map(fn (LogRecord $record) => $this->format($record), $records);
        $this->appendNewline = $oldNewline;

        return implode("\n", $formatted);
    }

    /**
     * Normalizes given $data.
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
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

        if ($data instanceof \DateTimeInterface) {
            return $this->formatDate($data);
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
     *
     * @inheritDoc
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
