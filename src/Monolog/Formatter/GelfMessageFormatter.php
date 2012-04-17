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
use Gelf\Message;

/**
 * Serializes a log message according to Wildfire's header requirements
 *
 * @author Matt Lehner <mlehner@gmail.com>
 */
class GelfMessageFormatter implements FormatterInterface
{
    /**
     * @var string the name of the system for the Gelf log message
     */
    protected $systemName;

    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contentPrefix;
    
    /**
     * Translates Monolog log levels to Graylog2 log priorities.
     */
    private $logLevels = array(
        Logger::DEBUG    => LOG_DEBUG,
        Logger::INFO     => LOG_INFO,
        Logger::WARNING  => LOG_WARNING,
        Logger::ERROR    => LOG_ERR,
        Logger::CRITICAL => LOG_CRIT,
        Logger::ALERT    => LOG_ALERT,
    );

    public function __construct($systemName = null, $extraPrefix = null, $contentPrefix = 'ctxt_')
    {
        $this->systemName = $systemName ?: gethostname();

        $this->extraPrefix = $extraPrefix;
        $this->contentPrefix = $contentPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $message = new Message();
        $message
            ->setTimestamp($record['datetime']->format('U.u'))
            ->setShortMessage((string) $record['message'])
            ->setFacility($record['channel'])
            ->setHost($this->systemName)
            ->setLine(isset($record['extra']['line']) ? $record['extra']['line'] : null)
            ->setFile(isset($record['extra']['file']) ? $record['extra']['file'] : null)
            ->setLevel($this->logLevels[ $record['level'] ]);

        // Do not duplicate these values in the additional fields
        unset($record['extra']['line']);
        unset($record['extra']['file']);

        foreach ($record['extra'] as $key => $val) {
            $message->setAdditional($this->extraPrefix . $key, is_scalar($val) ? $val : json_encode($val));
        }

        foreach ($record['context'] as $key => $val) {
            $message->setAdditional($this->contentPrefix . $key, is_scalar($val) ? $val : json_encode($val));
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        $messages = array();

        foreach ($records as $record) {
            $messages[] = $this->format($record);
        }

        return $messages;
    }
}
