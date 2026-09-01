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
use Monolog\Utils;

/**
 * Wraps another formatter to redact sensitive data from log records.
 *
 * It works in three passes so that it can mask secrets wherever they end up:
 *
 *  - Before delegating, it masks the value of any context/extra key whose name
 *    matches one of the configured sensitive keys (case-insensitive, recursive).
 *  - After delegating, it removes those same secret values from the wrapped
 *    formatter's output, which catches the copies masking the record cannot
 *    reach, e.g. a secret interpolated into the message by the
 *    PsrLogMessageProcessor or embedded in an exception message.
 *  - Last, it runs the configured regex patterns over the output, which catches
 *    secrets that only became strings once the inner formatter normalized
 *    objects (e.g. tokens buried in an exception stack trace). If a pattern
 *    cannot be applied, the record is dropped instead of emitted unredacted.
 *
 * On top of the sensitive keys, secrets are also read from the properties of the
 * objects in the record matching a constructor parameter marked
 * #[SensitiveParameter]. Those can only be handled by the output pass, as the
 * objects carrying them cannot be modified. This is the one part that reflects on
 * every logged object, so it can be turned off with $redactSensitiveParameters.
 *
 * Only string and array output can be swept, so wrapping a formatter which returns
 * objects (e.g. the ElasticaFormatter) leaves you with the key masking alone.
 *
 * Because it is a formatter rather than a processor, it is guaranteed to run
 * after every processor (both Logger- and Handler-level), so it always sees the
 * final record with all data at hand.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
final class RedactingFormatter implements WrappingFormatterInterface
{
    /**
     * Matches long token-like words (30+ alphanumerics, optionally prefixed like api_key_),
     * which are very likely to be secrets/tokens. Provided as an opt-in pattern as it may
     * cause collateral damage on legitimate long strings (hashes, UUIDs, base64, ...).
     */
    public const TOKEN_PATTERN = '{\b(?:[a-z]+_)*[a-zA-Z0-9]{30,}\b}';

    /**
     * How deep to walk the record and the output, mirrors NormalizerFormatter's default
     */
    private const MAX_DEPTH = 9;

    /** @var list<string> Lowercased sensitive keys */
    private array $sensitiveKeys;

    /** @var list<string> */
    private array $patterns;

    /** @var array<class-string, array<string, \ReflectionProperty>> */
    private array $sensitiveProperties = [];

    /**
     * @param FormatterInterface $formatter                 The formatter to wrap and whose output gets redacted
     * @param list<string>       $sensitiveKeys             Exact context/extra keys whose values to mask (case-insensitive)
     * @param list<string>       $patterns                  PCRE patterns to mask in the formatted output (e.g. self::TOKEN_PATTERN)
     * @param string             $mask                      Replacement token
     * @param bool               $redactSensitiveParameters Whether to also read secrets off the properties matching a
     *                                                      #[SensitiveParameter] constructor parameter, which requires
     *                                                      walking and reflecting on every object in the record
     * @param int                $minSecretLength           Minimum length a secret must have to be removed from the
     *                                                      output, to avoid short values like "pass" redacting
     *                                                      unrelated data
     *
     * @throws \InvalidArgumentException If a pattern is not a valid PCRE regex
     */
    public function __construct(
        private readonly FormatterInterface $formatter,
        array $sensitiveKeys = ['password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'auth', 'cookie'],
        array $patterns = [],
        private readonly string $mask = '[REDACTED]',
        private readonly bool $redactSensitiveParameters = true,
        private readonly int $minSecretLength = 5,
    ) {
        $this->sensitiveKeys = array_values(array_map('strtolower', $sensitiveKeys));

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException(sprintf('Invalid redaction pattern provided to RedactingFormatter: %s', var_export($pattern, true)));
            }
        }
        $this->patterns = array_values($patterns);
    }

    public function getWrappedFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    public function format(LogRecord $record)
    {
        // secrets must be collected before the keys get masked, as masking loses the values
        $secrets = $this->collectSecrets([$record]);

        return $this->sweep($this->formatter->format($this->redactRecord($record)), $secrets);
    }

    public function formatBatch(array $records)
    {
        $secrets = $this->collectSecrets($records);

        foreach ($records as $key => $record) {
            $records[$key] = $this->redactRecord($record);
        }

        return $this->sweep($this->formatter->formatBatch($records), $secrets);
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
    private function redactKeys(array $data, int $depth = 0): array
    {
        // context comes from userland and can hold a self-referencing array
        if ($depth > self::MAX_DEPTH) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (\is_string($key) && \in_array(strtolower($key), $this->sensitiveKeys, true)) {
                $data[$key] = $this->mask;
            } elseif (\is_array($value)) {
                $data[$key] = $this->redactKeys($value, $depth + 1);
            }
        }

        return $data;
    }

    /**
     * Gathers the secret values present in the given records, longest first
     *
     * @param  iterable<LogRecord> $records
     * @return list<string>
     */
    private function collectSecrets(iterable $records): array
    {
        $secrets = [];
        $seen = [];
        foreach ($records as $record) {
            $this->collect($record->context, $secrets, $seen, 0);
            $this->collect($record->extra, $secrets, $seen, 0);
        }

        if ([] === $secrets) {
            return [];
        }

        // a secret containing quotes/backslashes/newlines shows up escaped in json output,
        // so both forms have to be looked for
        $escapedSecrets = [];
        foreach ($secrets as $secret) {
            $escaped = substr(Utils::jsonEncode($secret, null, true), 1, -1);
            if ($escaped !== $secret && $escaped !== '') {
                $escapedSecrets[] = $escaped;
            }
        }

        $secrets = array_values(array_unique(array_merge($secrets, $escapedSecrets)));
        // longest first, so that overlapping secrets do not leave fragments behind
        usort($secrets, static fn (string $a, string $b) => \strlen($b) <=> \strlen($a));

        return $secrets;
    }

    /**
     * @param list<string>      $secrets
     * @param array<int, true>  $seen    Ids of the objects visited so far, to survive cyclic graphs
     */
    private function collect(mixed $data, array &$secrets, array &$seen, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                if (\is_string($key) && \in_array(strtolower($key), $this->sensitiveKeys, true)) {
                    // no need to recurse, the whole value is masked in the record anyway
                    $this->addSecret($value, $secrets);

                    continue;
                }

                $this->collect($value, $secrets, $seen, $depth + 1);
            }

            return;
        }

        // the array walk above is essentially free, reflecting on objects is not
        if (!\is_object($data) || !$this->redactSensitiveParameters) {
            return;
        }

        $id = spl_object_id($data);
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;

        foreach ($this->getSensitiveProperties($data) as $property) {
            if ($property->isInitialized($data)) {
                $this->addSecret($property->getValue($data), $secrets);
            }
        }

        // public properties only, matching what formatters get to see, but enough to reach
        // objects nested inside this one
        foreach (get_object_vars($data) as $value) {
            $this->collect($value, $secrets, $seen, $depth + 1);
        }
    }

    /**
     * Reflects the properties of an object matching a #[SensitiveParameter] constructor parameter
     *
     * Non-public properties are included as they can still leak through __toString(), and the
     * result is cached per class as reflecting on every logged object would be far too costly.
     *
     * @return array<string, \ReflectionProperty>
     */
    private function getSensitiveProperties(object $data): array
    {
        $class = $data::class;
        if (isset($this->sensitiveProperties[$class])) {
            return $this->sensitiveProperties[$class];
        }

        $names = Utils::getSensitiveParameterNames($class);

        // PHP 8.2+ wraps the arguments it hides from stack traces in a SensitiveParameterValue,
        // whose own constructor parameter is not marked. Matched by name and read as a property
        // so that it does not matter whether symfony/polyfill-php82 is installed on PHP 8.1:
        // naming the class makes PHPStan report either the @internal getValue() the polyfill
        // inherits, or a missing class when it is absent.
        if ('SensitiveParameterValue' === $class) {
            $names['value'] = true;
        }

        $properties = [];
        foreach ($names as $name => $_) {
            // walking up is required as a private property of a parent class is not
            // reachable through the child's ReflectionClass
            for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
                if ($reflection->hasProperty($name)) {
                    $properties[$name] = $reflection->getProperty($name);

                    break;
                }
            }
        }

        return $this->sensitiveProperties[$class] = $properties;
    }

    /**
     * @param list<string> $secrets
     */
    private function addSecret(mixed $value, array &$secrets): void
    {
        // a Stringable secret ends up in the output as its string form, so that is what
        // has to be looked for
        if ($value instanceof \Stringable) {
            try {
                $value = $value->__toString();
            } catch (\Throwable) {
                return;
            }
        }

        // ints/floats are left out on purpose, sweeping a digit run out of the output
        // would silently corrupt unrelated data
        if (\is_string($value) && $value !== '' && \strlen($value) >= $this->minSecretLength) {
            $secrets[] = $value;
        }
    }

    /**
     * Removes the collected secrets and applies the configured patterns to the formatter output,
     * recursing into arrays to support formatters that do not return a string (e.g. MongoDBFormatter).
     *
     * @param list<string> $secrets
     */
    private function sweep(mixed $formatted, array $secrets, int $depth = 0): mixed
    {
        if ([] === $this->patterns && [] === $secrets) {
            return $formatted;
        }

        // deeper than any formatter will render anyway, and a custom one may well
        // hand back a self-referencing array
        if ($depth > self::MAX_DEPTH) {
            return $formatted;
        }

        if (\is_string($formatted)) {
            if ([] !== $secrets) {
                $formatted = str_replace($secrets, $this->mask, $formatted);
            }
            if ([] !== $this->patterns) {
                $replaced = preg_replace($this->patterns, $this->mask, $formatted);
                if (null === $replaced) {
                    // the patterns could not be applied so the output cannot be trusted, drop it
                    // entirely. Neither the patterns (which may embed a secret themselves) nor the
                    // mask are reported, preg_last_error_msg() is one of a fixed set of PCRE strings
                    return '[RedactingFormatter dropped this record, pattern redaction failed with "'.preg_last_error_msg().'"]';
                }
                $formatted = $replaced;
            }

            return $formatted;
        }

        if (\is_array($formatted)) {
            foreach ($formatted as $key => $value) {
                $formatted[$key] = $this->sweep($value, $secrets, $depth + 1);
            }
        }

        return $formatted;
    }
}
