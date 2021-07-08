<?php declare(strict_types=1);

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
 * @see https://www.elastic.co/products/logstash
 * @see https://github.com/elastic/logstash/blob/master/logstash-core/src/main/java/org/logstash/Event.java
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
     * @var string the key for 'extra' fields from the Monolog record
     */
    protected $extraKey;

    /**
     * @var string the key for 'context' fields from the Monolog record
     */
    protected $contextKey;

    /**
     * @param string      $applicationName The application that sends the data, used as the "type" field of logstash
     * @param string|null $systemName      The system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     * @param string      $extraKey        The key for extra keys inside logstash "fields", defaults to extra
     * @param string      $contextKey      The key for context keys inside logstash "fields", defaults to context
     */
    public function __construct(string $applicationName, ?string $systemName = null, string $extraKey = 'extra', string $contextKey = 'context')
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->systemName = $systemName === null ? (string) gethostname() : $systemName;
        $this->applicationName = $applicationName;
        $this->extraKey = $extraKey;
        $this->contextKey = $contextKey;
    }

    /**
     * {@inheritDoc}
     */
    public function format(array $record): string
    {
        $record = parent::format($record);

        if (empty($record['datetime'])) {
            $record['datetime'] = gmdate('c');
        }
        $message = [
            '@timestamp' => $record['datetime'],
            '@version' => 1,
            'host' => $this->systemName,
        ];
        if (isset($record['message'])) {
            $message['message'] = $record['message'];
        }
        if (isset($record['channel'])) {
            $message['type'] = $record['channel'];
            $message['channel'] = $record['channel'];
        }
        if (isset($record['level_name'])) {
            $message['level'] = $record['level_name'];
        }
        if (isset($record['level'])) {
            $message['monolog_level'] = $record['level'];
        }
        if ($this->applicationName) {
            $message['type'] = $this->applicationName;
        }
        if (!empty($record['extra'])) {
            $message[$this->extraKey] = $record['extra'];
        }
        if (!empty($record['context'])) {
            $message[$this->contextKey] = $record['context'];
        }

        return $this->toJson($message) . "\n";
    }
}
