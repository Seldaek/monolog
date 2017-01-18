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

use DateTimeInterface;

/**
 * Encodes message information into JSON in a format compatible with Splunk.
 *
 * @author Daniel Sposito <daniel.g.sposito@gmail.com>
 */
class SplunkFormatter extends JsonFormatter
{
    /**
     * Prepends the timestamp parameter for indexing by Splunk.
     *
     * @see http://dev.splunk.com/view/logging-best-practices/SP-CAAADP6
     * @see \Monolog\Formatter\JsonFormatter::format()
     */
    public function format(array $record): string
    {
        if (isset($record["datetime"]) && ($record["datetime"] instanceof DateTimeInterface)) {
            $record = array_merge(
                array("timestamp" => $record["datetime"]->format("Y-m-d H:i:s.u T")),
                $record
            );

            unset($record["datetime"]);
        }

        return parent::format($record);
    }
}
