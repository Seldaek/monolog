<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Gelf\Message;
use Gelf\MessagePublisher;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Handler to send messages to a Graylog2 (http://www.graylog2.org) server 
 *
 * @author Matt Lehner <mlehner@gmail.com>
 */
class GelfHandler extends AbstractProcessingHandler
{
    /**
     * @var Gelf\MessagePublisher the publisher object that sends the message to the server
     */
    protected $publisher;

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

    /**
     * @param Gelf\MessagePublisher $publisher a publisher object
     * @param string $systemName the name of the system sending messages
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     * @param string $extraPrefix a string to prefix for all the 'extra' fields from Monolog record
     * @oaram string $contentPrefix a string to prefix for all the 'context' fields from a Monolog record
     */
    public function __construct(MessagePublisher $publisher, $systemName = null, $level = Logger::DEBUG,
                                $bubble = true, $extraPrefix = null, $contentPrefix = 'ctxt_')
    {
        parent::__construct($level, $bubble);

        $this->publisher = $publisher;
        $this->systemName = $systemName ?: gethostname();

        $this->extraPrefix = $extraPrefix;
        $this->contentPrefix = $contentPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->publisher = null;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record)
    {
        $message = new Message();
        $message
            ->setTimestamp($record['datetime']->format('U.u'))
            ->setShortMessage((string) $record['message'])
            ->setFullMessage((string) $record['formatted'])
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

        $this->publisher->publish($message);
    }
}
