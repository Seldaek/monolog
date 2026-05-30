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

use Monolog\LogRecord;

/**
 * Wraps another formatter to redact sensitive data from log records.
 *
 * It works in two passes so that it can mask secrets wherever they end up:
 *
 *  - Before delegating, it masks the value of any context/extra key whose name
 *    matches one of the configured sensitive keys (case-insensitive, recursive).
 *  - After delegating, it runs the configured regex patterns over the wrapped
 *    formatter's output. Running last means it also catches secrets that only
 *    became strings once the inner formatter normalized objects (e.g. tokens
 *    buried in an exception stack trace or a JsonSerializable payload).
 *
 * Because it is a formatter rather than a processor, it is guaranteed to run
 * after every processor (both Logger- and Handler-level), so it always sees the
 * final record with all data at hand.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
final class RedactingFormatter implements FormatterInterface
{
    /**
     * Matches long token-like words (30+ alphanumerics, optionally prefixed like api_key_),
     * which are very likely to be secrets/tokens. Provided as an opt-in pattern as it may
     * cause collateral damage on legitimate long strings (hashes, UUIDs, base64, ...).
     */
    public const TOKEN_PATTERN = '{\b(?:[a-z]+_)*[a-zA-Z0-9]{30,}\b}';

    /** @var list<string> Lowercased sensitive keys */
    private array $sensitiveKeys;

    /** @var list<string> */
    private array $patterns;

    /**
     * @param FormatterInterface $formatter     The formatter to wrap and whose output gets redacted
     * @param list<string>       $sensitiveKeys Exact context/extra keys whose values to mask (case-insensitive)
     * @param list<string>       $patterns      PCRE patterns to mask in the formatted output (e.g. self::TOKEN_PATTERN)
     * @param string             $mask          Replacement token
     *
     * @throws \InvalidArgumentException If a pattern is not a valid PCRE regex
     */
    public function __construct(
        private readonly FormatterInterface $formatter,
        array $sensitiveKeys = ['password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'auth', 'cookie'],
        array $patterns = [],
        private readonly string $mask = '[REDACTED]',
    ) {
        $this->sensitiveKeys = array_values(array_map('strtolower', $sensitiveKeys));

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException(sprintf('Invalid redaction pattern provided to RedactingFormatter: %s', var_export($pattern, true)));
            }
        }
        $this->patterns = array_values($patterns);
    }

    public function format(LogRecord $record)
    {
        return $this->sweep($this->formatter->format($this->redactRecord($record)));
    }

    public function formatBatch(array $records)
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->redactRecord($record);
        }

        return $this->sweep($this->formatter->formatBatch($records));
    }

    private function redactRecord(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactKeys($record->context),
            extra: $this->redactKeys($record->extra),
        );
    }

    /**
     * @param  array<mixed> $data
     * @return array<mixed>
     */
    private function redactKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && \in_array(strtolower($key), $this->sensitiveKeys, true)) {
                $data[$key] = $this->mask;
            } elseif (\is_array($value)) {
                $data[$key] = $this->redactKeys($value);
            }
        }

        return $data;
    }

    /**
     * Applies the configured patterns to the formatter output, recursing into arrays
     * to support formatters that do not return a string (e.g. MongoDBFormatter).
     */
    private function sweep(mixed $formatted): mixed
    {
        if ([] === $this->patterns) {
            return $formatted;
        }

        if (\is_string($formatted)) {
            return preg_replace($this->patterns, $this->mask, $formatted) ?? $formatted;
        }

        if (\is_array($formatted)) {
            foreach ($formatted as $key => $value) {
                $formatted[$key] = $this->sweep($value);
            }
        }

        return $formatted;
    }
}
