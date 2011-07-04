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
 * Serializes a log message according to Wildfire's header requirements
 *
 * @author Eric Clemmons (@ericclemmons) <eric@uxdriven.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class WildfireFormatter extends LineFormatter
{
    /**
     * Similar to LineFormatter::SIMPLE_FORMAT, except without the "[%datetime%]"
     */
    const SIMPLE_FORMAT = "%message% %context% %extra%";

    /**
     * Translates Monolog log levels to Wildfire levels.
     */
    private $logLevels = array(
        Logger::DEBUG    => 'LOG',
        Logger::INFO     => 'INFO',
        Logger::WARNING  => 'WARN',
        Logger::ERROR    => 'ERROR',
        Logger::CRITICAL => 'ERROR',
        Logger::ALERT    => 'ERROR',
    );

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        // Retrieve the line and file if set and remove them from the formatted extra
        $file = $line = '';
        if (isset($record['extra']['file'])) {
            $file = $record['extra']['file'];
            unset($record['extra']['file']);
        }
        if (isset($record['extra']['line'])) {
            $line = $record['extra']['line'];
            unset($record['extra']['line']);
        }

        // Format record according with LineFormatter
        $message = parent::format($record);

        // Create JSON object describing the appearance of the message in the console
        $json = json_encode(array(
            array(
                'Type'  => $this->logLevels[$record['level']],
                'File'  => $file,
                'Line'  => $line,
                'Label' => $record['channel'],
            ),
            $message,
        ));

        // The message itself is a serialization of the above JSON object + it's length
        return sprintf(
            '%s|%s|',
            strlen($json),
            $json
        );
    }

    public function formatBatch(array $records)
    {
        throw new \BadMethodCallException('Batch formatting does not make sense for the WildfireFormatter');
    }
}