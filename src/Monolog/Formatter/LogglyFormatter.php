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

/**
 * Encodes message information into JSON in a format compatible with Loggly.
 *
 * @author Adam Pancutt <adam@pancutt.com>
 */
class LogglyFormatter extends JsonFormatter
{
    /**
     * Overrides the default batch mode to new lines for compatibility with the
     * Loggly bulk API.
     */
    public function __construct(int $batchMode = self::BATCH_MODE_NEWLINES, bool $appendNewline = false)
    {
        parent::__construct($batchMode, $appendNewline);
    }

    /**
     * Appends the 'timestamp' parameter for indexing by Loggly.
     *
     * @see https://www.loggly.com/docs/automated-parsing/#json
     * @see \Monolog\Formatter\JsonFormatter::format()
     */
    public function format(array $record): string
    {
        if (isset($record["datetime"]) && ($record["datetime"] instanceof \DateTimeInterface)) {
            $record["timestamp"] = $record["datetime"]->format("Y-m-d\TH:i:s.uO");
            unset($record["datetime"]);
        }

        return parent::format($record);
    }
}
