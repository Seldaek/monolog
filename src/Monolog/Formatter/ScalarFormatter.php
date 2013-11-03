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

use Monolog\Formatter\FormatterInterface;

/**
 * Formats data into an associative array of scalar values.
 * Objects and arrays will be JSON encoded.
 *
 * @author Andrew Lawson <adlawson@gmail.com>
 */
class ScalarFormatter implements FormatterInterface
{
    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * @param string $dateFormat
     */
    public function __construct($dateFormat = \DateTime::ISO8601)
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $formatted = array();
        $record = $this->exposeContext($record);

        foreach ($record as $key => $value) {
            $formatted[$key] = $this->normalizeValue($value);
        }

        return $formatted;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        $formatted = array();

        foreach ($records as $record) {
            $formatted[] = $this->format($record);
        }

        return $formatted;
    }

    /**
     * @param mixed $data
     * @return string
     */
    protected function encodeData($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    /**
     * @param array $record
     * @return array
     */
    protected function exposeContext(array $record)
    {
        if (isset($record['context'])) {
            $context = $record['context'];

            if (isset($context['exception'])) {
                $record['context']['exception'] = $this->normalizeException($context['exception']);
            }
        }

        return $record;
    }

    /**
     * @param Exception $e
     * @return string
     */
    protected function normalizeException(\Exception $e)
    {
        return array(
            'message' => $e->getMessage(),
            'code'  => $e->getCode(),
            'class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'debug' => $e->getTrace()
        );
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeValue($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format($this->dateFormat);
        } elseif ($value instanceof \Exception) {
            return $this->encodeData($this->normalizeException($value));
        } elseif (is_array($value) || is_object($value)) {
            return $this->encodeData($value);
        }

        return $value;
    }
}
