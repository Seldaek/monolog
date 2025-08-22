<?php
declare(strict_types=1);

namespace Monolog\Processor;

use Monolog\LogRecord;

final class RedactSensitiveProcessor implements ProcessorInterface
{
    /** @var string[] */
    private array $sensitiveKeys;

    /** @var string[] */
    private array $patterns;

    private string $mask;

    /**
     * @param string[] $sensitiveKeys exact keys to redact in context/extra (case-insensitive)
     * @param string[] $patterns PCRE regex patterns to mask inside string values (e.g. '/Bearer\\s+[A-Za-z0-9\\._-]+/')
     * @param string   $mask replacement token
     */
    public function __construct(array $sensitiveKeys = ['password','passwd','pwd','secret','token','api_key','apikey','authorization','auth','cookie'], array $patterns = [], string $mask = 'REDACTED')
    {
        $this->sensitiveKeys = array_map('strtolower', $sensitiveKeys);
        $this->patterns = $patterns;
        $this->mask = $mask;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->sanitize($record->context);
        $extra   = $this->sanitize($record->extra);

        $message = $record->message;
        foreach ($this->patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                continue; // ignore invalid pattern instead of throwing inside logging path
            }
            $message = preg_replace($pattern, $this->mask, $message) ?? $message;
        }

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra
        );
    }

    /** @param mixed $value */
    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? strtolower($k) : $k;
                if (is_string($k) && in_array($key, $this->sensitiveKeys, true)) {
                    $sanitized[$k] = $this->mask;
                } else {
                    $sanitized[$k] = $this->sanitize($v);
                }
            }
            return $sanitized;
        }

        if (is_string($value) && $this->patterns) {
            foreach ($this->patterns as $pattern) {
                if (@preg_match($pattern, '') === false) {
                    continue;
                }
                $value = preg_replace($pattern, $this->mask, $value) ?? $value;
            }
        }

        return $value;
    }
}
