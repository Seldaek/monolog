<?php

namespace Tellecore\Monolog\Handler;

use Gelf\Message;
use Gelf\MessagePublisher;

use Monolog\Formatter\SimpleFormatter;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Handler to send messages to a Graylog2 (http://www.graylog2.org) server 
 *
 * @author Matt Lehner <mlehner@gmail.com>
 */
class GelfHandler extends AbstractProcessingHandler
{
    /*
     * @var Gelf\MessagePublisher the publisher object that sends the message to the server
     */
    protected $publisher;

    /*
     * @var string the name of the system for the Gelf log message
     */
    protected $system_name;

    /*
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extra_prefix;

    /*
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $context_prefix;

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
     * @param string $system_name the name of the system sending messages
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     * @param string $extra_prefix a string to prefix for all the 'extra' fields from Monolog record
     * @oaram string $context_prefix a string to prefix for all the 'context' fields from a Monolog record
     */
    public function __construct(MessagePublisher $publisher, $system_name = null, $level = Logger::DEBUG, $bubble = true, $extra_prefix = null, $context_prefix = null)
    {
        parent::__construct($level, $bubble);

        $this->publisher = $publisher;
        $this->system_name = $system_name ?: gethostname();

        $this->extra_prefix = $extra_prefix;
        $this->context_prefix = $context_prefix;
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
            ->setHost($this->system_name)
            ->setLine(isset($record['extra']['line']) ? $record['extra']['line'] : null)
            ->setFile(isset($record['extra']['file']) ? $record['extra']['file'] : null)
            ->setLevel($this->logLevels[ $record['level'] ]);

        // Do not duplicate these values in the additional fields
        unset($record['extra']['line']);
        unset($record['extra']['file']);

        foreach ($record['extra'] as $key => $val)
        {
            $message->setAdditional($this->extra_prefix . $key, is_scalar($val) ? $val : json_encode($val));
        }

        foreach ($record['context'] as $key => $val)
        {
            $message->setAdditional($this->context_prefix . $key, is_scalar($val) ? $val : json_encode($val));
        }

        $this->publisher->publish($message);
    }
}
