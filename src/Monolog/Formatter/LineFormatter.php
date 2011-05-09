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
 */
class LineFormatter implements FormatterInterface
{
    const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %extra%\n";
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
        foreach ($vars as $var => $val) {
            if (is_array($val)) {
                $strval = array();
                foreach ($val as $subvar => $subval) {
                    $strval[] = $subvar.': '.$this->convertToString($subval);
                }
                $replacement = $strval ? $var.'('.implode(', ', $strval).')' : '';
                $output = str_replace('%'.$var.'%', $replacement, $output);
            } else {
                $output = str_replace('%'.$var.'%', $this->convertToString($val), $output);
            }
        }
        foreach ($vars['extra'] as $var => $val) {
            $output = str_replace('%extra.'.$var.'%', $this->convertToString($val), $output);
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

    private function convertToString($data)
    {
        if (is_scalar($data) || (is_object($data) && method_exists($data, '__toString'))) {
            return (string) $data;
        }

        return serialize($data);
    }
}
