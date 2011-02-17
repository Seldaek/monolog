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

class SimpleFormatter implements FormatterInterface
{
    const SIMPLE_FORMAT = "[%date%] %log%.%level%: %message%\n";
    const SIMPLE_DATE = "Y-m-d H:i:s";

    protected $format;
    protected $dateFormat;

    public function __construct($format = null, $dateFormat = null)
    {
        $this->format = $format ?: self::SIMPLE_FORMAT;
        $this->dateFormat = $dateFormat ?: self::SIMPLE_DATE;
    }

    public function format($log, $level, $message)
    {
        $defaults = array(
            'log' => $log,
            'level' => $level,
            'date' => date($this->dateFormat),
        );

        if (is_array($message)) {
            $vars = array_merge($defaults, $message);
        } else {
            $vars = $defaults;
            $vars['message'] = $message;
        }

        foreach ($vars as $var => $val) {
            $message = str_replace('%'.$var.'%', $val, $message);
        }
        return $message;
    }
}
