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
 * Encodes message into JSON in a format compatible with Stackdriver.
 *
 * @author Dmitry Tarasov <tarasovdyu@gmail.com>
 */
class StackdriverFormatter extends JsonFormatter
{
    /**
     * Transforms log level information into Stackdriver 'severity' field.
     *
     * @see https://cloud.google.com/logging/docs/reference/v2/rest/v2/LogEntry
     * @see \Monolog\Formatter\JsonFormatter::format()
     */
    public function format(array $record): string
    {
        $record['severity'] = $record['level_name'];
        unset($record['level'], $record['level_name']);

        return parent::format($record);
    }
}
