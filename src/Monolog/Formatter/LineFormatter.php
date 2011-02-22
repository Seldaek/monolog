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
 * Formats incoming messages into a one-line string
 *
 * This is especially useful for logging to files
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class LineFormatter implements FormatterInterface
{
    const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message%\n";
    const SIMPLE_DATE = "Y-m-d H:i:s";

    protected $format;
    protected $dateFormat;

    public function __construct($format = null, $dateFormat = null)
    {
        $this->format = $format ?: self::SIMPLE_FORMAT;
        $this->dateFormat = $dateFormat ?: self::SIMPLE_DATE;
    }

    public function format($message)
    {
        $vars = $message;
        $vars['datetime'] = $vars['datetime']->format($this->dateFormat);

        if (is_array($message['message'])) {
            unset($vars['message']);
            $vars = array_merge($vars, $message['message']);
        }

        $output = $this->format;
        foreach ($vars as $var => $val) {
            if (!is_array($val)) {
                $output = str_replace('%'.$var.'%', $val, $output);
            }
        }
        foreach ($vars['extra'] as $var => $val) {
            $output = str_replace('%extra.'.$var.'%', $val, $output);
        }
        $message['message'] = $output;
        return $message;
    }
}
