<?php

namespace AppBundle\Monitoring\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Push logs directly to Elasticsearch and format them according to Logstash specification
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ElasticsearchLogstashHandler extends AbstractProcessingHandler
{
    private $endpoint;
    private $index;
    private $client;
    private $responses;

    public function __construct($endpoint = 'http://elasticsearch:9200', $index = 'monolog', HttpClientInterface $client = null, $level = Logger::DEBUG, $bubble = true)
    {
        if (!class_exists(HttpClientInterface::class)) {
            throw new \LogicException(sprintf('%s handler needs symfony/http-client, please run `composer require symfony/http-client`.', __CLASS__));
        }

        parent::__construct($level, $bubble);
        $this->endpoint = $endpoint;
        $this->index = $index;
        $this->client = $client ?: HttpClient::create();
        $this->responses = new \SplObjectStorage();
    }

    protected function write(array $record)
    {
        $this->sendToElasticsearch(array($record));
    }

    protected function getDefaultFormatter()
    {
        return new LogstashFormatter('application', null, null, 'ctxt_', LogstashFormatter::V1);
    }

    private function sendToElasticsearch(array $documents)
    {
        $body = '';
        foreach ($documents as $document) {
            $body .= json_encode([
                'index' => [
                    '_index' => $this->index,
                    '_type' => '_doc',
                ],
            ]);
            $body .= "\n";
            $body .= $document['formatted'];
            $body .= "\n";
        }

        $response = $this->client->request('POST', $this->endpoint.'/_bulk', [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->responses->attach($response);

        foreach ($this->client->stream($this->responses, 0) as $response => $chunk) {
            if (!$chunk->isTimeout() && $chunk->isFirst()) {
                $this->responses->detach($response);
            }
        }
    }
}
