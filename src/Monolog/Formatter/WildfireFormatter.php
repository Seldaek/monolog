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
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 */
class WildfireFormatter implements FormatterInterface
{
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

        $message = array('message' => $record['message']);
        if ($record['context']) {
            $message['context'] = $record['context'];
        }
        if ($record['extra']) {
            $message['extra'] = $record['extra'];
        }
        if (count($message) === 1) {
            $message = reset($message);
        }

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