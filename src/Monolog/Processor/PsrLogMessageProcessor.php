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
    const SIMPLE_DATE = "Y-m-d\TH:i:sP";

    protected $dateFormat;

    /**
     * @param string $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct(string $dateFormat = null)
    {
        $this->dateFormat = null === $dateFormat ? static::SIMPLE_DATE : $dateFormat;
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
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, "__toString"))) {
                $replacements['{' . $key . '}'] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements['{'.$key.'}'] = $val->format($this->dateFormat);
            } elseif (is_object($val)) {
                $replacements['{'.$key.'}'] = '[object '.get_class($val).']';
            } else {
                $replacements['{'.$key.'}'] = '['.gettype($val).']';
            }
        }

        $record['message'] = strtr($record['message'], $replacements);

        return $record;
    }
}
