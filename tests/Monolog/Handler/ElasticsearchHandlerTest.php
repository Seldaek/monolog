<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Formatter\ElasticsearchFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Test\TestCase;
use Monolog\Level;
use Elasticsearch\Client;
use Elastic\Elasticsearch\Client as Client8;
use Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\ClientBuilder as ClientBuilder8;

/**
 * @group Elasticsearch
 */
class ElasticsearchHandlerTest extends TestCase
{
    protected Client|Client8 $client;

    /**
     * @var array Default handler options
     */
    protected array $options = [
        'index' => 'my_index',
        'type'  => 'doc_type',
        'op_type' => 'index',
    ];

    public function setUp(): void
    {
        $hosts = ['http://elastic:changeme@127.0.0.1:9200'];
        $this->client = $this->getClientBuilder()
            ->setHosts($hosts)
            ->build();

        try {
            $this->client->info();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not connect to Elasticsearch on 127.0.0.1:9200');
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->client);
    }

    /**
     * @covers Monolog\Handler\ElasticsearchHandler::setFormatter
     */
    public function testSetFormatter()
    {
        $handler = new ElasticsearchHandler($this->client);
        $formatter = new ElasticsearchFormatter('index_new', 'type_new');
        $handler->setFormatter($formatter);
        $this->assertInstanceOf('Monolog\Formatter\ElasticsearchFormatter', $handler->getFormatter());
        $this->assertEquals('index_new', $handler->getFormatter()->getIndex());
        $this->assertEquals('type_new', $handler->getFormatter()->getType());
    }

    /**
     * @covers Monolog\Handler\ElasticsearchHandler::setFormatter
     */
    public function testSetFormatterInvalid()
    {
        $handler = new ElasticsearchHandler($this->client);
        $formatter = new NormalizerFormatter();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ElasticsearchHandler is only compatible with ElasticsearchFormatter');

        $handler->setFormatter($formatter);
    }

    /**
     * @covers Monolog\Handler\ElasticsearchHandler::__construct
     * @covers Monolog\Handler\ElasticsearchHandler::getOptions
     */
    public function testOptions()
    {
        $expected = [
            'index' => $this->options['index'],
            'type' => $this->options['type'],
            'ignore_error' => false,
            'op_type' => $this->options['op_type'],
        ];

        if ($this->client instanceof Client8 || $this->client::VERSION[0] === '7') {
            $expected['type'] = '_doc';
        }

        $handler = new ElasticsearchHandler($this->client, $this->options);
        $this->assertEquals($expected, $handler->getOptions());
    }

    /**
     * @covers       Monolog\Handler\ElasticsearchHandler::bulkSend
     * @dataProvider providerTestConnectionErrors
     */
    public function testConnectionErrors($ignore, $expectedError)
    {
        $hosts = ['http://127.0.0.1:1'];
        $client = $this->getClientBuilder()
            ->setHosts($hosts)
            ->build();

        $handlerOpts = ['ignore_error' => $ignore];
        $handler = new ElasticsearchHandler($client, $handlerOpts);

        if ($expectedError) {
            $this->expectException($expectedError[0]);
            $this->expectExceptionMessage($expectedError[1]);
            $handler->handle($this->getRecord());
        } else {
            $this->assertFalse($handler->handle($this->getRecord()));
        }
    }

    public static function providerTestConnectionErrors(): array
    {
        return [
            [false, ['RuntimeException', 'Error sending messages to Elasticsearch']],
            [true, false],
        ];
    }

    /**
     * Integration test using localhost Elasticsearch server
     *
     * @covers Monolog\Handler\ElasticsearchHandler::__construct
     * @covers Monolog\Handler\ElasticsearchHandler::handleBatch
     * @covers Monolog\Handler\ElasticsearchHandler::bulkSend
     * @covers Monolog\Handler\ElasticsearchHandler::getDefaultFormatter
     */
    public function testHandleBatchIntegration()
    {
        $msg = $this->getRecord(Level::Error, 'log', context: ['foo' => 7, 'bar', 'class' => new \stdClass], datetime: new \DateTimeImmutable("@0"));

        $expected = $msg->toArray();
        $expected['datetime'] = $msg['datetime']->format(\DateTime::ISO8601);
        $expected['context'] = [
            'class' => ["stdClass" => []],
            'foo' => 7,
            0 => 'bar',
        ];

        $hosts = ['http://elastic:changeme@127.0.0.1:9200'];
        $client = $this->getClientBuilder()
            ->setHosts($hosts)
            ->build();
        $handler = new ElasticsearchHandler($client, $this->options);
        $handler->handleBatch([$msg]);

        // check document id from ES server response
        if ($client instanceof Client8) {
            $messageBody = $client->getTransport()->getLastResponse()->getBody();

            $info = json_decode((string) $messageBody, true);
            $this->assertNotNull($info, 'Decoding failed');

            $documentId = $this->getCreatedDocIdV8($info);
            $this->assertNotEmpty($documentId, 'No elastic document id received');
        } else {
            $documentId = $this->getCreatedDocId($client->transport->getLastConnection()->getLastRequestInfo());
            $this->assertNotEmpty($documentId, 'No elastic document id received');
        }

        // retrieve document source from ES and validate
        $document = $this->getDocSourceFromElastic(
            $client,
            $this->options['index'],
            $this->options['type'],
            $documentId
        );

        $this->assertEquals($expected, $document);

        // remove test index from ES
        $client->indices()->delete(['index' => $this->options['index']]);
    }

    /**
     * Return last created document id from ES response
     *
     * @param array $info Elasticsearch last request info
     */
    protected function getCreatedDocId(array $info): ?string
    {
        $data = json_decode($info['response']['body'], true);

        if (!empty($data['items'][0]['index']['_id'])) {
            return $data['items'][0]['index']['_id'];
        }

        return null;
    }

    /**
     * Return last created document id from ES response
     *
     * @param  array       $data Elasticsearch last request info
     * @return string|null
     */
    protected function getCreatedDocIdV8(array $data)
    {
        if (!empty($data['items'][0]['index']['_id'])) {
            return $data['items'][0]['index']['_id'];
        }

        return null;
    }

    /**
     * Retrieve document by id from Elasticsearch
     *
     * @return array<mixed>
     */
    protected function getDocSourceFromElastic(Client|Client8 $client, string $index, string $type, string $documentId): array
    {
        $params = [
            'index' => $index,
            'id' => $documentId,
        ];

        if (!$client instanceof Client8 && $client::VERSION[0] !== '7') {
            $params['type'] = $type;
        }

        $data = $client->get($params);

        if (!empty($data['_source'])) {
            return $data['_source'];
        }

        return [];
    }

    /**
     * @return ClientBuilder|ClientBuilder8
     */
    private function getClientBuilder()
    {
        if (class_exists(ClientBuilder8::class)) {
            return ClientBuilder8::create();
        }

        return ClientBuilder::create();
    }
}
