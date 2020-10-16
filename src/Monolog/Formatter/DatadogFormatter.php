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
 * Serializes a log message to take advantages of Datadog capacities
 *
 * @see https://docs.datadoghq.com/logs/log_collection/?tab=http#reserved-attributes
 * @see https://docs.datadoghq.com/logs/log_collection/?tab=http#how-to-get-the-most-of-your-application-logs
 *
 * @author Tristan Bessoussa <tristan.bessoussa@gmail.com>
 */
class DatadogFormatter extends NormalizerFormatter
{
    /**
     * @var string|null
     */
    protected $systemName;

    /**
     * @var string|null
     */
    protected $applicationName;

    /**
     * @var string|null
     */
    protected $env;

    /**
     * @var string|null
     */
    protected $source;

    /**
     * @var string|null
     */
    protected $loggerName;

    /**
     * @param string|null $applicationName The name of the application or service generating the log events used as the "source" Datadog attribute.
     *                                     It is used to switch from Logs to APM, so make sure you define the same value when you use both products.
     * @param string|null $systemName      The system/machine name, used as the "host" field of Datadog, defaults to the hostname of the machine
     * @param string|null $env      The system/machine name, used as the "host" field of Datadog, defaults to the hostname of the machine
     * @param string|null $source          This corresponds to the integration name: the technology from which the log originated.
     *                                     Must be one of the "Pipeline Library" (https://app.datadoghq.eu/logs/pipelines/pipeline/library)
     * @param string|null $loggerName      Name of the logger, defaults to monolog
     */
    public function __construct(?string $applicationName = null, ?string $systemName = null, ?string $env = null, ?string $source = 'php', ?string $loggerName = 'monolog')
    {
        parent::__construct();
        $this->applicationName = $applicationName;
        $this->systemName = $systemName === null ? gethostname() : $systemName;
        $this->env = $env;
        $this->source = $source;
        $this->loggerName = $loggerName;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record): string
    {
        $record = parent::format($record);

        if (empty($record['logger.name'])) {
            $record['logger.name'] = $this->loggerName;
        }

        if (empty($record['host'])) {
            $record['host'] = $this->systemName;
        }

        if (empty($record['source'])) {
            $record['ddsource'] = $this->source;
        }

        if (isset($record['level_name'])) {
            $record['status'] = $record['level_name'];
            unset($record['level_name']);
        }

        if (null !== $this->applicationName) {
            $record['service'] = $this->applicationName;
        }

        if (null !== $this->env) {
            $record['env'] = $this->env;
        }

        if (!empty($record['context'] && !empty($record['context']['exception']) && !empty($record['context']['exception']['class']))) {
            $record['error.kind'] = $record['context']['exception']['class'];
        }

        return $this->toJson($record) . "\n";
    }
}
