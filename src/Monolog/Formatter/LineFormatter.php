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

use Closure;
use Monolog\Utils;
use Monolog\LogRecord;

/**
 * Formats incoming records into a one-line string
 *
 * This is especially useful for logging to files
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class LineFormatter extends NormalizerFormatter
{
    public const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    protected string $format;
    protected bool $allowInlineLineBreaks;
    protected bool $ignoreEmptyContextAndExtra;
    protected bool $includeStacktraces;
    protected ?int $maxLevelNameLength = null;
    protected string $indentStacktraces = '';
    protected Closure|null $stacktracesParser = null;
    protected string $basePath = '';

    private ?string $parsedFormat = null;
    /** @var list<string|array{string, string|null}> literal text, or [name, null] for %name% and [context|extra, key] for %context.key% */
    private array $formatParts = [];
    /** @var list<array{string, string}> */
    private array $formatKeys = [];

    /**
     * @param string|null $format                The format of the message
     * @param string|null $dateFormat            The format of the timestamp: one supported by DateTime::format
     * @param bool        $allowInlineLineBreaks Whether to allow inline line breaks in log entries
     */
    public function __construct(?string $format = null, ?string $dateFormat = null, bool $allowInlineLineBreaks = false, bool $ignoreEmptyContextAndExtra = false, bool $includeStacktraces = false)
    {
        $this->format = $format === null ? static::SIMPLE_FORMAT : $format;
        $this->allowInlineLineBreaks = $allowInlineLineBreaks;
        $this->ignoreEmptyContextAndExtra = $ignoreEmptyContextAndExtra;
        $this->includeStacktraces($includeStacktraces);
        parent::__construct($dateFormat);
    }

    /**
     * Setting a base path will hide the base path from exception and stack trace file names to shorten them
     * @return $this
     */
    public function setBasePath(string $path = ''): self
    {
        if ($path !== '') {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        $this->basePath = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function includeStacktraces(bool $include = true, ?Closure $parser = null): self
    {
        $this->includeStacktraces = $include;
        if ($this->includeStacktraces) {
            $this->allowInlineLineBreaks = true;
            $this->stacktracesParser = $parser;
        }

        return $this;
    }

    /**
     * Indent stack traces to separate them a bit from the main log record messages
     *
     * @param  string $indent The string used to indent, for example "    "
     * @return $this
     */
    public function indentStacktraces(string $indent): self
    {
        $this->indentStacktraces = $indent;

        return $this;
    }

    /**
     * @return $this
     */
    public function allowInlineLineBreaks(bool $allow = true): self
    {
        $this->allowInlineLineBreaks = $allow;

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreEmptyContextAndExtra(bool $ignore = true): self
    {
        $this->ignoreEmptyContextAndExtra = $ignore;

        return $this;
    }

    /**
     * Allows cutting the level name to get fixed-length levels like INF for INFO, ERR for ERROR if you set this to 3 for example
     *
     * @param  int|null $maxLevelNameLength Maximum characters for the level name. Set null for infinite length (default)
     * @return $this
     */
    public function setMaxLevelNameLength(?int $maxLevelNameLength = null): self
    {
        $this->maxLevelNameLength = $maxLevelNameLength;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function format(LogRecord $record): string
    {
        $vars = parent::format($record);

        if ($this->maxLevelNameLength !== null) {
            $vars['level_name'] = substr($vars['level_name'], 0, $this->maxLevelNameLength);
        }

        // $format is protected, so a subclass may change it after construction
        if ($this->parsedFormat !== $this->format) {
            $this->parseFormat(array_keys($vars));
        }

        // keys printed on their own are left out of %context% and %extra%
        $remaining = ['context' => $vars['context'], 'extra' => $vars['extra']];
        foreach ($this->formatKeys as [$group, $key]) {
            unset($remaining[$group][$key]);
        }

        // the line is built from the parsed format, so placeholders in logged values are never expanded
        $output = '';
        foreach ($this->formatParts as $part) {
            if (\is_string($part)) {
                $output .= $part;
                continue;
            }

            [$name, $key] = $part;
            if ($key !== null) {
                // %context.x% and %extra.x% of a missing key are removed
                if (\array_key_exists($key, $vars[$name])) {
                    $output .= $this->stringify($vars[$name][$key]);
                }
            } elseif (isset($remaining[$name])) {
                if (!$this->ignoreEmptyContextAndExtra || \count($remaining[$name]) > 0) {
                    $output .= $this->stringify($remaining[$name]);
                }
            } elseif (\array_key_exists($name, $vars)) {
                $output .= $this->stringify($vars[$name]);
            } else {
                $output .= '%'.$name.'%';
            }
        }

        return $output;
    }

    /**
     * @param array<int|string> $names the record fields, only those are placeholders so a stray % cannot eat the next one
     */
    private function parseFormat(array $names): void
    {
        $this->formatParts = $this->formatKeys = [];

        $names = implode('|', array_map(static fn ($name) => preg_quote((string) $name), $names));
        preg_match_all('{%(?:(context|extra)\.([^%]*)|('.$names.'))%}', $this->format, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $offset = 0;
        foreach ($matches as $match) {
            if ($match[0][1] > $offset) {
                $this->formatParts[] = substr($this->format, $offset, $match[0][1] - $offset);
            }
            $offset = $match[0][1] + \strlen($match[0][0]);

            if (isset($match[3])) {
                $this->formatParts[] = [$match[3][0], null];
            } elseif (isset($match[1], $match[2])) {
                $this->formatParts[] = $this->formatKeys[] = [$match[1][0], $match[2][0]];
            }
        }
        if ($offset < \strlen($this->format)) {
            $this->formatParts[] = substr($this->format, $offset);
        }

        $this->parsedFormat = $this->format;
    }

    public function formatBatch(array $records): string
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    /**
     * @param mixed $value
     */
    public function stringify($value): string
    {
        return $this->replaceNewlines($this->convertToString($value));
    }

    protected function normalizeException(\Throwable $e, int $depth = 0): string
    {
        $str = $this->formatException($e);

        $previous = $e->getPrevious();
        while ($previous instanceof \Throwable) {
            $depth++;
            if ($depth > $this->maxNormalizeDepth) {
                $str .= "\n[previous exception] Over " . $this->maxNormalizeDepth . ' levels deep, aborting normalization';
                break;
            }
            $str .= "\n[previous exception] " . $this->formatException($previous);
            $previous = $previous->getPrevious();
        }

        return $str;
    }

    /**
     * @param mixed $data
     */
    protected function convertToString($data): string
    {
        if (null === $data || \is_bool($data)) {
            return var_export($data, true);
        }

        if (\is_scalar($data)) {
            return (string) $data;
        }

        return $this->toJson($data, true);
    }

    protected function replaceNewlines(string $str): string
    {
        if ($this->allowInlineLineBreaks) {
            if (0 === strpos($str, '{') || 0 === strpos($str, '[')) {
                $str = preg_replace('/(?<!\\\\)\\\\[rn]/', "\n", $str);
                if (null === $str) {
                    $pcreErrorCode = preg_last_error();

                    throw new \RuntimeException('Failed to run preg_replace: ' . $pcreErrorCode . ' / ' . preg_last_error_msg());
                }
            }

            return $str;
        }

        return str_replace(["\r\n", "\r", "\n"], ' ', $str);
    }

    private function formatException(\Throwable $e): string
    {
        $str = '[object] (' . Utils::getClass($e) . '(code: ' . $e->getCode();
        if ($e instanceof \SoapFault) {
            if (isset($e->faultcode)) {
                $str .= ' faultcode: ' . $e->faultcode;
            }

            if (isset($e->faultactor)) {
                $str .= ' faultactor: ' . $e->faultactor;
            }

            if (isset($e->detail)) {
                if (\is_string($e->detail)) {
                    $str .= ' detail: ' . $e->detail;
                } elseif (\is_object($e->detail) || \is_array($e->detail)) {
                    $str .= ' detail: ' . $this->toJson($e->detail, true);
                }
            }
        }

        $file = $e->getFile();
        if ($this->basePath !== '') {
            $file = preg_replace('{^'.preg_quote($this->basePath).'}', '', $file);
        }

        $str .= '): ' . $e->getMessage() . ' at ' . strtr((string) $file, DIRECTORY_SEPARATOR, '/') . ':' . $e->getLine() . ')';

        if ($this->includeStacktraces) {
            $str .= $this->stacktracesParser($e);
        }

        return $str;
    }

    private function stacktracesParser(\Throwable $e): string
    {
        $trace = $e->getTraceAsString();

        if ($this->basePath !== '') {
            $trace = preg_replace('{^(#\d+ )' . preg_quote($this->basePath) . '}m', '$1', $trace) ?? $trace;
        }

        if ($this->stacktracesParser !== null) {
            $trace = $this->stacktracesParserCustom($trace);
        }

        if ($this->indentStacktraces !== '') {
            $trace = str_replace("\n", "\n{$this->indentStacktraces}", $trace);
        }

        if (trim($trace) === '') {
            return '';
        }

        return "\n{$this->indentStacktraces}[stacktrace]\n{$this->indentStacktraces}" . strtr($trace, DIRECTORY_SEPARATOR, '/') . "\n";
    }

    private function stacktracesParserCustom(string $trace): string
    {
        return implode("\n", array_filter(array_map($this->stacktracesParser, explode("\n", $trace)), fn ($line) => is_string($line) && trim($line) !== ''));
    }
}
