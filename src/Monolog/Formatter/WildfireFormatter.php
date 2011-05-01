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
 */
class WildfireFormatter extends LineFormatter implements FormatterInterface
{
    /**
     * Similar to LineFormatter::SIMPLE_FORMAT, except without the "[%datetime%]"
     */
    const SIMPLE_FORMAT = "%channel%: %message% %extra%";

    /**
     * Translates Monolog log levels to Wildfire levels.
     */
    private $logLevels = array(
        Logger::DEBUG   => 'LOG',
        Logger::INFO    => 'INFO',
        Logger::WARNING => 'WARN',
        Logger::ERROR   => 'ERROR',
    );

    /**
     * {@inheritdoc}
     */
    public function __construct($format = null, $dateFormat = null)
    {
        $this->format = $format ?: self::SIMPLE_FORMAT;
        $this->dateFormat = $dateFormat ?: self::SIMPLE_DATE;
    }

    /**
     * {@inheritdoc}
     */
    public function format(Array $record)
    {
        // Format record according with LineFormatter
        $formatted = parent::format($record);

        // Create JSON object describing the appearance of the message in the console
        $json = json_encode(array(
            array(
                'Type'  =>  $this->logLevels[$record['level']],
                'File'  =>  '',
                'Line'  =>  '',
            ),
            $formatted['message'],
        ));

        // The message itself is a serialization of the above JSON object + it's length
        $formatted['message'] = sprintf(
            '%s|%s|',
            strlen($json),
            $json
        );

        return $formatted;
    }

}