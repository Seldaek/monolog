<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Utils;

/**
 * Handler sending logs to browser's javascript console with no browser extension required
 *
 * @author Olivier Poitrey <rs@dailymotion.com>
 */
class BrowserConsoleHandler extends AbstractProcessingHandler
{
    protected static $initialized = false;
    protected static $records = [];

    /**
     * {@inheritDoc}
     *
     * Formatted output may contain some formatting markers to be transferred to `console.log` using the %c format.
     *
     * Example of formatted string:
     *
     *     You can do [[blue text]]{color: blue} or [[green background]]{background-color: green; color: white}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('[[%channel%]]{macro: autolabel} [[%level_name%]]{font-weight: bold} %message%');
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record): void
    {
        // Accumulate records
        static::$records[] = $record;

        // Register shutdown handler if not already done
        if (!static::$initialized) {
            static::$initialized = true;
            $this->registerShutdownFunction();
        }
    }

    /**
     * Convert records to javascript console commands and send it to the browser.
     * This method is automatically called on PHP shutdown if output is HTML or Javascript.
     */
    public static function send(): void
    {
        $format = static::getResponseFormat();
        if ($format === 'unknown') {
            return;
        }

        if (count(static::$records)) {
            if ($format === 'html') {
                static::writeOutput('<script>' . static::generateScript() . '</script>');
            } elseif ($format === 'js') {
                static::writeOutput(static::generateScript());
            }
            static::resetStatic();
        }
    }

    public function close(): void
    {
        self::resetStatic();
    }

    public function reset()
    {
        parent::reset();

        self::resetStatic();
    }

    /**
     * Forget all logged records
     */
    public static function resetStatic(): void
    {
        static::$records = [];
    }

    /**
     * Wrapper for register_shutdown_function to allow overriding
     */
    protected function registerShutdownFunction(): void
    {
        if (PHP_SAPI !== 'cli') {
            register_shutdown_function(['Monolog\Handler\BrowserConsoleHandler', 'send']);
        }
    }

    /**
     * Wrapper for echo to allow overriding
     */
    protected static function writeOutput(string $str): void
    {
        echo $str;
    }

    /**
     * Checks the format of the response
     *
     * If Content-Type is set to application/javascript or text/javascript -> js
     * If Content-Type is set to text/html, or is unset -> html
     * If Content-Type is anything else -> unknown
     *
     * @return string One of 'js', 'html' or 'unknown'
     */
    protected static function getResponseFormat(): string
    {
        // Check content type
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0) {
                // This handler only works with HTML and javascript outputs
                // text/javascript is obsolete in favour of application/javascript, but still used
                if (stripos($header, 'application/javascript') !== false || stripos($header, 'text/javascript') !== false) {
                    return 'js';
                }
                if (stripos($header, 'text/html') === false) {
                    return 'unknown';
                }
                break;
            }
        }

        return 'html';
    }

    private static function generateScript(): string
    {
        $script = [];
        foreach (static::$records as $record) {
            $context = static::dump('Context', $record['context']);
            $extra = static::dump('Extra', $record['extra']);

            if (empty($context) && empty($extra)) {
                $script[] = static::call_array('log', static::handleStyles($record['formatted']));
            } else {
                $script = array_merge(
                    $script,
                    [static::call_array('groupCollapsed', static::handleStyles($record['formatted']))],
                    $context,
                    $extra,
                    [static::call('groupEnd')]
                );
            }
        }

        return "(function (c) {if (c && c.groupCollapsed) {\n" . implode("\n", $script) . "\n}})(console);";
    }

    private static function handleStyles(string $formatted): array
    {
        $args = [static::quote('font-weight: normal')];
        $format = '%c' . $formatted;
        preg_match_all('/\[\[(.*?)\]\]\{([^}]*)\}/s', $format, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach (array_reverse($matches) as $match) {
            $args[] = static::quote(static::handleCustomStyles($match[2][0], $match[1][0]));
            $args[] = '"font-weight: normal"';

            $pos = $match[0][1];
            $format = Utils::substr($format, 0, $pos) . '%c' . $match[1][0] . '%c' . Utils::substr($format, $pos + strlen($match[0][0]));
        }

        array_unshift($args, static::quote($format));

        return $args;
    }

    private static function handleCustomStyles(string $style, string $string): string
    {
        static $colors = ['blue', 'green', 'red', 'magenta', 'orange', 'black', 'grey'];
        static $labels = [];

        return preg_replace_callback('/macro\s*:(.*?)(?:;|$)/', function (array $m) use ($string, &$colors, &$labels) {
            if (trim($m[1]) === 'autolabel') {
                // Format the string as a label with consistent auto assigned background color
                if (!isset($labels[$string])) {
                    $labels[$string] = $colors[count($labels) % count($colors)];
                }
                $color = $labels[$string];

                return "background-color: $color; color: white; border-radius: 3px; padding: 0 2px 0 2px";
            }

            return $m[1];
        }, $style);
    }

    private static function dump(string $title, array $dict): array
    {
        $script = [];
        $dict = array_filter($dict);
        if (empty($dict)) {
            return $script;
        }
        $script[] = static::call('log', static::quote('%c%s'), static::quote('font-weight: bold'), static::quote($title));
        foreach ($dict as $key => $value) {
            $value = json_encode($value);
            if (empty($value)) {
                $value = static::quote('');
            }
            $script[] = static::call('log', static::quote('%s: %o'), static::quote($key), $value);
        }

        return $script;
    }

    private static function quote(string $arg): string
    {
        return '"' . addcslashes($arg, "\"\n\\") . '"';
    }

    private static function call(...$args): string
    {
        $method = array_shift($args);

        return static::call_array($method, $args);
    }

    private static function call_array(string $method, array $args): string
    {
        return 'c.' . $method . '(' . implode(', ', $args) . ');';
    }
}
