<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Wolfram Huesken <woh@m18.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;

/**
 * Creates Bunyan compatible log output
 *
 * @link https://github.com/trentm/node-bunyan
 * @author Wolfram Huesken <woh@m18.io>
 */
class BunyanFormatter extends JsonFormatter
{
    /**
     * The service/app is going to stop or become unusable now - An operator should definitely look into this soon
     * @var int
     */
    const LEVEL_FATAL = 60;

    /**
     * Fatal for a particular request, but the service/app continues servicing other requests
     * An operator should look at this soon(ish)
     * @var int
     */
    const LEVEL_ERROR = 50;

    /**
     * A note on something that should probably be looked at by an operator eventually
     * @var int
     */
    const LEVEL_WARN = 40;

    /**
     * Detail on regular operation
     * @var int
     */
    const LEVEL_INFO = 30;

    /**
     * Anything else, i.e. too verbose to be included in "info" level
     * @var int
     */
    const LEVEL_DEBUG = 20;

    /**
     * Logging from external libraries used by your app or very detailed application logging
     * @var int
     */
    const LEVEL_TRACE = 10;

    /**
     * @var int
     */
    const BUNYAN_VERSION = 0;

    /**
     * Map monolog log levels to Bunyan style
     * @var array
     */
    private $logLevelMapping = array(
        Logger::DEBUG     => self::LEVEL_DEBUG,
        Logger::INFO      => self::LEVEL_INFO,
        Logger::NOTICE    => self::LEVEL_INFO,
        Logger::WARNING   => self::LEVEL_WARN,
        Logger::ERROR     => self::LEVEL_ERROR,
        Logger::CRITICAL  => self::LEVEL_ERROR,
        Logger::ALERT     => self::LEVEL_FATAL,
        Logger::EMERGENCY => self::LEVEL_FATAL
    );

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        return parent::format($this->getBunyanLogEvent($record));
    }

    /**
     * @param int $logLevel
     * @return int
     */
    public function getBunyanLogLevel($logLevel) {
       return $this->logLevelMapping[$logLevel];
    }

    /**
     * {@inheritdoc}
     */
    protected function formatBatchJson(array $records)
    {
        $output = array();
        foreach ($records as $record) {
            $output[] = $this->getBunyanLogEvent($record);
        }

        return parent::formatBatchJson($output);
    }

    /**
     * @param array $record
     * @return array
     */
    private function getBunyanLogEvent(array $record) {
        $logEvent = array_merge($record['context'], $record['extra']);
        $logEvent['level'] = $this->logLevelMapping[$record['level']];
        $logEvent['msg'] = $record['message'];
        $logEvent['name'] = $record['channel'];
        $logEvent['time'] = $this->getTimestamp();
        $logEvent['hostname'] = gethostname();
        $logEvent['pid'] = getmypid();
        $logEvent['v'] = static::BUNYAN_VERSION;

        return $logEvent;
    }

    /**
     * Return date + time with 4-digit microsends with UTC timezone
     * @return string
     */
    private function getTimestamp() {
        $mTime = sprintf('%.4F', microtime(true));
        $datetime = \DateTime::createFromFormat('U.u', $mTime, new \DateTimeZone('UTC'));
        return $datetime->format('Y-m-d\TH:i:s.') . substr($mTime, -4) . 'Z';
    }
}
