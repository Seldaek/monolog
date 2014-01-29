<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

/**
 * Handler sending logs to browser's javascript console with no browser extension required
 *
 * @author Olivier Poitrey <rs@dailymotion.com>
 */
class BrowserConsoleHandler extends AbstractProcessingHandler
{
    static protected $initialized = false;
    static protected $records = array();

    /**
     * {@inheritDoc}
     *
     * Formatted output may contain some formatting markers to be transfered to `console.log` using the %c format.
     *
     * Example of formatted string:
     *
     *     You can do [blue text]{color: blue} or [green background]{background-color: green; color: white}
     *
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter('[%channel%]{macro: autolabel} [%level_name%]{font-weight: bold} %message%');
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        // Accumulate records
        self::$records[] = $record;

        // Register shutdown handler if not already done
        if (PHP_SAPI !== 'cli' && !self::$initialized) {
            self::$initialized = true;
            register_shutdown_function(array('Monolog\Handler\BrowserConsoleHandler', 'send'));
        }
    }

    /**
     * Convert records to javascript console commands and send it to the browser.
     * This method is automatically called on PHP shutdown if output is HTML.
     */
    static public function send()
    {
        // Check content type
        foreach (headers_list() as $header) {
            if (strpos(strtolower($header), 'content-type:') === 0) {
                if (strpos(strtolower($header), 'text/html') === false) {
                    // This handler does only work with HTML outputs
                    return;
                }
                break;
            }
        }

        if (count(self::$records)) {
            print '<script>' . self::generateScript() . '</script>';
            self::reset();
        }
    }

    /**
     * Forget all logged records
     */
    static public function reset()
    {
        self::$records = array();
    }

    static public function generateScript()
    {
        $script = array();
        foreach (self::$records as $record) {
            $context = self::dump('Context', $record['context']);
            $extra = self::dump('Extra', $record['extra']);

            if (empty($context) && empty($extra)) {
                $script[] = self::call_array('log', self::handleStyles($record['formatted']));
            } else {
                $script = array_merge($script,
                    array(self::call_array('groupCollapsed', self::handleStyles($record['formatted']))),
                    $context,
                    $extra,
                    array(self::call('groupEnd'))
                );
            }
        }
        return "(function(c){if (c && c.groupCollapsed) {\n" . implode("\n", $script) . "\n}})(console);";
    }

    static public function handleStyles($formatted)
    {
        $args = array(self::quote('font-weight: normal'));
        $format = '%c' . $formatted;
        $self = 'Monolog\Handler\BrowserConsoleHandler';
        $format = preg_replace_callback('/\[(.*?)\]\{(.*?)\}/', function($m) use(&$args, $self) {
            $args[] = $self::quote($self::handleCustomStyles($m[2], $m[1]));
            $args[] = $self::quote('font-weight: normal');
            return '%c' . $m[1] . '%c';
        }, $format);

        array_unshift($args, self::quote($format));
        return $args;
    }

    static public function handleCustomStyles($style, $string)
    {
        $self = 'Monolog\Handler\BrowserConsoleHandler';
        return preg_replace_callback('/macro\s*:(.*?)(?:;|$)/', function($m) use($string, $self) {
            switch (trim($m[1])) {
                case 'autolabel':
                    return $self::macroAutolabel($string);
                    break;
                default:
                    return $m[1];
            }
        }, $style);
    }

    /**
     * Format the string as a label with consistent auto assigned background color
     */
    static public function macroAutolabel($string)
    {
        static $colors = array('blue', 'green', 'red', 'magenta', 'orange', 'black', 'grey');
        static $labels = array();

        if (!isset($labels[$string])) {
            $labels[$string] = $colors[count($labels) % count($colors)];
        }
        $color = $labels[$string];

        return "background-color: $color; color: white; border-radius: 3px; padding: 0 2px 0 2px";
    }

    static public function dump($title, array $dict)
    {
        $script = array();
        $dict = array_filter($dict);
        if (empty($dict)) {
            return $script;
        }
        $script[] = self::call('log', self::quote('%c%s'), self::quote('font-weight: bold'), self::quote($title));
        foreach ($dict as $key => $value) {
            $value = json_encode($value);
            if (empty($value)) {
                $value = self::quote('');
            }
            $script[] = self::call('log', self::quote('%s: %o'), self::quote($key), $value);
        }
        return $script;
    }

    static public function quote($arg)
    {
        return '"' . addcslashes($arg, "\"\n") . '"';
    }

    static public function call()
    {
        $args = func_get_args();
        $method = array_shift($args);
        return self::call_array($method, $args);
    }

    static public function call_array($method, array $args)
    {
        return 'c.' . $method . '(' . implode(', ', $args) . ');';
    }
}
