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


    public function __construct($systemName = null, $applicationName = null, $extraPrefix = null, $contextPrefix = 'ctxt_')
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
            '@message' => $record['message'],
            '@tags' => array($record['channel']),
            '@source' => $this->systemName
        );

        if ($this->applicationName) {
            $message['@type'] = $this->applicationName;
        }
        $message['@fields'] = array();
        $message['@fields']['channel'] = $record['channel'];
        $message['@fields']['level'] = $record['level'];

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

        return json_encode($message);
    }
}
