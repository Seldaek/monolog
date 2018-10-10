<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

/**
 * Processes a record's message according to PSR-3 rules
 *
 * It replaces {foo} with the value from $context['foo']
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class PsrLogMessageProcessor
{
    const SIMPLE_DATE = "Y-m-d\TH:i:s.uP";

    /** @var ?string */
    private $dateFormat;

    /** @var bool */
    private $removeUsedContextFields;

    /**
     * @param ?string $dateFormat              The format of the timestamp: one supported by DateTime::format
     * @param bool    $removeUsedContextFields If set to true the fields interpolated into message gets unset
     */
    public function __construct(?string $dateFormat = null, bool $removeUsedContextFields = false)
    {
        $this->dateFormat = $dateFormat;
        $this->removeUsedContextFields = $removeUsedContextFields;
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        if (false === strpos($record['message'], '{')) {
            return $record;
        }

        $replacements = [];
        foreach ($record['context'] as $key => $val) {
            $placeholder = '{' . $key . '}';
            if (strpos($record['message'], $placeholder) === false) {
                continue;
            }

            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, "__toString"))) {
                $replacements[$placeholder] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                if (!$this->dateFormat && $val instanceof \Monolog\DateTimeImmutable) {
                    // handle monolog dates using __toString if no specific dateFormat was asked for
                    // so that it follows the useMicroseconds flag
                    $replacements[$placeholder] = (string) $val;
                } else {
                    $replacements[$placeholder] = $val->format($this->dateFormat ?: static::SIMPLE_DATE);
                }
            } elseif (is_object($val)) {
                $replacements[$placeholder] = '[object '.get_class($val).']';
            } elseif (is_array($val)) {
                $replacements[$placeholder] = 'array'.@json_encode($val);
            } else {
                $replacements[$placeholder] = '['.gettype($val).']';
            }

            if ($this->removeUsedContextFields) {
                unset($record['context'][$key]);
            }
        }

        $record['message'] = strtr($record['message'], $replacements);

        return $record;
    }
}
