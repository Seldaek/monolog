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

use Monolog\Logger;

/**
 * Formats incoming records into a one-line string
 *
 * This is especially useful for logging to files
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class LineFormatter implements FormatterInterface
{
    const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
    const SIMPLE_DATE = "Y-m-d H:i:s";

    protected $format;
    protected $dateFormat;

    /**
     * @param string $format The format of the message
     * @param string $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct($format = null, $dateFormat = null)
    {
        $this->format = $format ?: static::SIMPLE_FORMAT;
        $this->dateFormat = $dateFormat ?: static::SIMPLE_DATE;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $vars = $record;
        $vars['datetime'] = $vars['datetime']->format($this->dateFormat);

        $output = $this->format;
        foreach ($vars['extra'] as $var => $val) {
            if (false !== strpos($output, '%extra.'.$var.'%')) {
                $output = str_replace('%extra.'.$var.'%', $this->convertToString($val), $output);
                unset($vars['extra'][$var]);
            }
        }
        foreach ($vars as $var => $val) {
            $output = str_replace('%'.$var.'%', $this->convertToString($val), $output);
        }

        return $output;
    }

    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    protected function convertToString($data)
    {
        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        return stripslashes(json_encode($this->normalize($data)));
    }

    protected function normalize($data)
    {
        if (null === $data || is_scalar($data)) {
            return $data;
        }

        if (is_array($data) || $data instanceof \Traversable) {
            $normalized = array();

            foreach ($data as $key => $value) {
                $normalized[$key] = $this->normalize($value);
            }

            return $normalized;
        }

        if (is_resource($data)) {
            return '[resource]';
        }

        return sprintf("[object] (%s: %s)", get_class($data), json_encode($data));
    }
}
