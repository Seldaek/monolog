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
class LogstashFormatter extends NormalizerFormatter
{
    const V0 = 0;
    const V1 = 1;

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
     * @var integer logstash format version to use
     */
    protected $version;

    /**
     * @param string $applicationName the application that sends the data, used as the "type" field of logstash
     * @param string $systemName      the system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     * @param string $extraPrefix     prefix for extra keys inside logstash "fields"
     * @param string $contextPrefix   prefix for context keys inside logstash "fields", defaults to ctxt_
     */
    public function __construct($applicationName, $systemName = null, $extraPrefix = null, $contextPrefix = 'ctxt_', $version = self::V0)
    {
        // logstash requires a ISO 8601 format date
        parent::__construct('c');

        $this->systemName = $systemName ?: gethostname();
        $this->applicationName = $applicationName;
        $this->extraPrefix = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
        $this->version = $version;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        if ($this->version === self::V1) {
            $message = $this->formatV1($record);
        } else {
            $message = $this->formatV0($record);
        }

        return $this->toJson($message) . "\n";
    }

    protected function formatV0(array $record)
    {
        $message = array(
            '@timestamp' => $record['datetime'],
            '@message' => $record['message'],
            '@tags' => array($record['channel']),
            '@source' => $this->systemName,
            '@fields' => array(
                'channel' => $record['channel'],
                'level' => $record['level']
            )
        );

        if ($this->applicationName) {
            $message['@type'] = $this->applicationName;
        }

        if (isset($record['extra']['server'])) {
            $message['@source_host'] = $record['extra']['server'];
        }
        if (isset($record['extra']['url'])) {
            $message['@source_path'] = $record['extra']['url'];
        }
        foreach ($record['extra'] as $key => $val) {
            $message['@fields'][$this->extraPrefix . $key] = $val;
        }

        foreach ($record['context'] as $key => $val) {
            $message['@fields'][$this->contextPrefix . $key] = $val;
        }

        return $message;
    }

    protected function formatV1(array $record)
    {
        $message = array(
            '@timestamp' => $record['datetime'],
            '@version' => 1,
            'message' => $record['message'],
            'host' => $this->systemName,
            'type' => $record['channel'],
            'channel' => $record['channel'],
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

        return $message;
    }
}
