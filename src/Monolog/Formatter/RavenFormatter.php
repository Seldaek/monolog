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
use \Raven_Client;

/**
 * Serializes a log message for Raven (https://github.com/getsentry/raven-php)
 *
 * @author Marc Abramowitz <marc@marc-abramowitz.com>
 */
class RavenFormatter extends NormalizerFormatter
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    private $logLevels = array(
        Logger::DEBUG    => Raven_Client::DEBUG,
        Logger::INFO     => Raven_Client::INFO,
        Logger::WARNING  => Raven_Client::WARNING,
        Logger::ERROR    => Raven_Client::ERROR,
    );

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        $record['level'] = $this->logLevels[$record['level']];
        $record['message'] = $record['channel'] . ': ' . $record['message'];

        if (isset($record['context']['context']))
        {
            $record['context'] = $record['context']['context'];
        }

        return $record;
    }
}
