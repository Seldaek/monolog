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

use Monolog\DateTimeImmutable;
use Monolog\Utils;
use Throwable;

/**
 * Normalizes incoming records to remove objects/resources so it's easier to dump to various targets
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class NormalizerFormatter implements FormatterInterface
{
    public const SIMPLE_DATE = "Y-m-d\TH:i:sP";

    /** @var string */
    protected $dateFormat;
    /** @var int */
    protected $maxNormalizeDepth = 9;
    /** @var int */
    protected $maxNormalizeItemCount = 1000;

    /** @var int */
    private $jsonEncodeOptions = Utils::DEFAULT_JSON_FLAGS;

    /**
     * @param string|null $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct(?string $dateFormat = null)
    {
        $this->dateFormat = null === $dateFormat ? static::SIMPLE_DATE : $dateFormat;
        if (!function_exists('json_encode')) {
            throw new \RuntimeException('PHP\'s json extension is required to use Monolog\'s NormalizerFormatter');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        return $this->normalize($record);
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }

        return $records;
    }

    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(string $dateFormat): self
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    /**
     * The maximum number of normalization levels to go through
     */
    public function getMaxNormalizeDepth(): int
    {
        return $this->maxNormalizeDepth;
    }

    public function setMaxNormalizeDepth(int $maxNormalizeDepth): self
    {
        $this->maxNormalizeDepth = $maxNormalizeDepth;

        return $this;
    }

    /**
     * The maximum number of items to normalize per level
     */
    public function getMaxNormalizeItemCount(): int
    {
        return $this->maxNormalizeItemCount;
    }

    public function setMaxNormalizeItemCount(int $maxNormalizeItemCount): self
    {
        $this->maxNormalizeItemCount = $maxNormalizeItemCount;

        return $this;
    }

    /**
     * Enables `json_encode` pretty print.
     */
    public function setJsonPrettyPrint(bool $enable): self
    {
        if ($enable) {
            $this->jsonEncodeOptions |= JSON_PRETTY_PRINT;
        } else {
            $this->jsonEncodeOptions &= ~JSON_PRETTY_PRINT;
        }

        return $this;
    }

    /**
     * @param  mixed                      $data
     * @return int|bool|string|null|array
     */
    protected function normalize($data, int $depth = 0)
    {
        if ($depth > $this->maxNormalizeDepth) {
            return 'Over ' . $this->maxNormalizeDepth . ' levels deep, aborting normalization';
        }

        if (null === $data || is_scalar($data)) {
            if (is_float($data)) {
                if (is_infinite($data)) {
                    return ($data > 0 ? '' : '-') . 'INF';
                }
                if (is_nan($data)) {
                    return 'NaN';
                }
            }

            return $data;
        }

        if (is_array($data)) {
            $normalized = [];

            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > $this->maxNormalizeItemCount) {
                    $normalized['...'] = 'Over ' . $this->maxNormalizeItemCount . ' items ('.count($data).' total), aborting normalization';
                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if ($data instanceof \DateTimeInterface) {
            return $this->formatDate($data);
        }

        if (is_object($data)) {
            if ($data instanceof Throwable) {
                return $this->normalizeException($data, $depth);
            }

            if ($data instanceof \JsonSerializable) {
                $value = $data->jsonSerialize();
            } elseif (method_exists($data, '__toString')) {
                $value = $data->__toString();
            } else {
                // the rest is normalized by json encoding and decoding it
                $value = json_decode($this->toJson($data, true), true);
            }

            return [Utils::getClass($data) => $value];
        }

        if (is_resource($data)) {
            return sprintf('[resource(%s)]', get_resource_type($data));
        }

        return '[unknown('.gettype($data).')]';
    }

    /**
     * @return array
     */
    protected function normalizeException(Throwable $e, int $depth = 0)
    {
        if ($e instanceof \JsonSerializable) {
            return (array) $e->jsonSerialize();
        }

        $data = [
            'class' => Utils::getClass($e),
            'message' => $e->getMessage(),
            'code' => (int) $e->getCode(),
            'file' => $e->getFile().':'.$e->getLine(),
        ];

        if ($e instanceof \SoapFault) {
            if (isset($e->faultcode)) {
                $data['faultcode'] = $e->faultcode;
            }

            if (isset($e->faultactor)) {
                $data['faultactor'] = $e->faultactor;
            }

            if (isset($e->detail)) {
                if (is_string($e->detail)) {
                    $data['detail'] = $e->detail;
                } elseif (is_object($e->detail) || is_array($e->detail)) {
                    $data['detail'] = $this->toJson($e->detail, true);
                }
            }
        }

        $trace = $e->getTrace();
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                $data['trace'][] = $frame['file'].':'.$frame['line'];
            }
        }

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous, $depth + 1);
        }

        return $data;
    }

    /**
     * Return the JSON representation of a value
     *
     * @param  mixed             $data
     * @throws \RuntimeException if encoding fails and errors are not ignored
     * @return string            if encoding fails and ignoreErrors is true 'null' is returned
     */
    protected function toJson($data, bool $ignoreErrors = false): string
    {
        return Utils::jsonEncode($data, $this->jsonEncodeOptions, $ignoreErrors);
    }

    protected function formatDate(\DateTimeInterface $date)
    {
        // in case the date format isn't custom then we defer to the custom DateTimeImmutable
        // formatting logic, which will pick the right format based on whether useMicroseconds is on
        if ($this->dateFormat === self::SIMPLE_DATE && $date instanceof DateTimeImmutable) {
            return (string) $date;
        }

        return $date->format($this->dateFormat);
    }

    public function addJsonEncodeOption(int $option)
    {
        $this->jsonEncodeOptions |= $option;
    }

    public function removeJsonEncodeOption(int $option)
    {
        $this->jsonEncodeOptions &= ~$option;
    }
}
