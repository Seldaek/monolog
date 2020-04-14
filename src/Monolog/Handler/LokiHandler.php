<?php

declare(strict_types=1);

namespace Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LokiFormatter;
use Monolog\Logger;

class LokiHandler extends AbstractProcessingHandler
{
    /**
     * @var string the scheme, hostname and port to the Loki system
     */
    protected $entrypoint;

    /**
     * @var array the identifiers for Basic Authentication to the Loki system
     */
    protected $basicAuth = [];

    /**
     * @var string the name of the system sending log messages to Loki
     */
    protected $systemName;

    /**
     * @var array the list of default context variables to be sent to the Loki system
     */
    protected $globalContext = [];

    /**
     * @var array the list of default labels to be sent to the Loki system
     */
    protected $globalLabels = [];

    public function __construct(array $apiConfig, $level = Logger::DEBUG, $bubble = true)
    {
        if (!function_exists('json_encode')) {
            throw new \RuntimeException('PHP\'s json extension is required to use Monolog\'s LokiHandler');
        }
        parent::__construct($level, $bubble);
        $this->entrypoint = $this->getEntrypoint($apiConfig['entrypoint']);
        $this->globalContext = $apiConfig['context'] ?? [];
        $this->globalLabels = $apiConfig['labels'] ?? [];
        $this->systemName = $apiConfig['client_name'] ?? null;
        if (isset($apiConfig['auth']['basic'])) {
            $this->basicAuth = (2 === count($apiConfig['auth']['basic'])) ? $apiConfig['auth']['basic'] : [];
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LokiFormatter($this->globalLabels, $this->globalContext, $this->systemName);
    }

    private function getEntrypoint(string $entrypoint): string
    {
        if ('/' !== substr($entrypoint, -1)) {
            return $entrypoint;
        }

        return substr($entrypoint, 0, -1);
    }

    private function sendPacket(array $packet): void
    {
        $payload = json_encode($packet, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $url = sprintf('%s/loki/api/v1/push', $this->entrypoint);
        $ch = curl_init($url);
        if (!empty($this->basicAuth)) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, implode(':', $this->basicAuth));
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]
        );

        Curl\Util::execute($ch);
    }

    protected function write(array $record): void
    {
        $this->sendPacket(['streams' => [$record['formatted']]]);
    }

    public function handleBatch(array $records): void
    {
        $rows = [];
        foreach ($records as $record) {
            $record = $this->processRecord($record);
            $rows[] = $record;
        }

        $this->sendPacket(['streams' => $rows]);
    }
}
