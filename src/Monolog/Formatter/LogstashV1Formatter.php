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

/**
 * Serializes a log message to Logstash Event Format
 *
 * @see http://logstash.net/
 * @see https://github.com/logstash/logstash/blob/master/lib/logstash/event.rb
 *
 * @author Tim Mower <timothy.mower@gmail.com>
 */
class LogstashV1Formatter extends NormalizerFormatter
{
    /**
     * @var string the name of the system for the Logstash log message, used to fill the @source field
     */
    protected $systemName;

    /**
     * @var string an application name for the Logstash log message, used to fill the @type field
     */
    protected $applicationName;

    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contextPrefix;

    /**
     * @param string $applicationName the application that sends the data, used as the "type" field of logstash
     * @param string $systemName      the system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     * @param string $extraPrefix     prefix for extra keys inside logstash "fields"
     * @param string $contextPrefix   prefix for context keys inside logstash "fields", defaults to ctxt_
     */
    public function __construct($applicationName, $systemName = null, $extraPrefix = null, $contextPrefix = 'ctxt_')
    {
        //log stash requires a ISO 8601 format date
        parent::__construct('c');

        $this->systemName = $systemName ?: gethostname();
        $this->applicationName = $applicationName;

        $this->extraPrefix = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);
        $message = array(
            '@timestamp' => $record['datetime'],
            '@version' => 1,
            'message' => $record['message'],
            'host' => $this->systemName,
            'type' => $record['channel'],
            'level' => $record['level_name']
        );

        if ($this->applicationName) {
            $message['type'] = $this->applicationName;
        }

        foreach ($record['extra'] as $key => $val) {
            $message[$this->extraPrefix . $key] = $val;
        }

        foreach ($record['context'] as $key => $val) {
            $message[$this->contextPrefix . $key] = $val;
        }

        return $this->toJson($message) . "\n";
    }
}
