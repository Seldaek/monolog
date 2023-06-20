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

use Monolog\Formatter\ElasticaFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Test\TestCase;
use Monolog\Level;
use Elastica\Client;
use Elastica\Request;
use Elastica\Response;

/**
 * @group Elastica
 */
class ElasticaHandlerTest extends TestCase
{
    /**
     * @var Client mock
     */
    protected Client $client;

    /**
     * @var array Default handler options
     */
    protected array $options = [
        'index' => 'my_index',
        'type'  => 'doc_type',
    ];

    public function setUp(): void
    {
        // Elastica lib required
        if (!class_exists("Elastica\Client")) {
            $this->markTestSkipped("ruflin/elastica not installed");
        }

        // base mock Elastica Client object
        $this->client = $this->getMockBuilder('Elastica\Client')
            ->onlyMethods(['addDocuments'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->client);
    }

    /**
     * @covers Monolog\Handler\ElasticaHandler::write
     * @covers Monolog\Handler\ElasticaHandler::handleBatch
     * @covers Monolog\Handler\ElasticaHandler::bulkSend
     * @covers Monolog\Handler\ElasticaHandler::getDefaultFormatter
     */
    public function testHandle()
    {
        // log message
        $msg = $this->getRecord(Level::Error, 'log', context: ['foo' => 7, 'bar', 'class' => new \stdClass], datetime: new \DateTimeImmutable("@0"));

        // format expected result
        $formatter = new ElasticaFormatter($this->options['index'], $this->options['type']);
        $expected = [$formatter->format($msg)];

        // setup ES client mock
        $this->client->expects($this->any())
            ->method('addDocuments')
            ->with($expected);

        // perform tests
        $handler = new ElasticaHandler($this->client, $this->options);
        $handler->handle($msg);
        $handler->handleBatch([$msg]);
    }

    /**
     * @covers Monolog\Handler\ElasticaHandler::setFormatter
     */
    public function testSetFormatter()
    {
        $handler = new ElasticaHandler($this->client);
        $formatter = new ElasticaFormatter('index_new', 'type_new');
        $handler->setFormatter($formatter);
        $this->assertInstanceOf('Monolog\Formatter\ElasticaFormatter', $handler->getFormatter());
        $this->assertEquals('index_new', $handler->getFormatter()->getIndex());
        $this->assertEquals('type_new', $handler->getFormatter()->getType());
    }

    /**
     * @covers                   Monolog\Handler\ElasticaHandler::setFormatter
     */
    public function testSetFormatterInvalid()
    {
        $handler = new ElasticaHandler($this->client);
        $formatter = new NormalizerFormatter();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ElasticaHandler is only compatible with ElasticaFormatter');

        $handler->setFormatter($formatter);
    }

    /**
     * @covers Monolog\Handler\ElasticaHandler::__construct
     * @covers Monolog\Handler\ElasticaHandler::getOptions
     */
    public function testOptions()
    {
        $expected = [
            'index' => $this->options['index'],
            'type' => $this->options['type'],
            'ignore_error' => false,
        ];
        $handler = new ElasticaHandler($this->client, $this->options);
        $this->assertEquals($expected, $handler->getOptions());
    }

    /**
     * @covers       Monolog\Handler\ElasticaHandler::bulkSend
     * @dataProvider providerTestConnectionErrors
     */
    public function testConnectionErrors($ignore, $expectedError)
    {
        $clientOpts = ['host' => '127.0.0.1', 'port' => 1];
        $client = new Client($clientOpts);
        $handlerOpts = ['ignore_error' => $ignore];
        $handler = new ElasticaHandler($client, $handlerOpts);

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
     * Integration test using localhost Elastic Search server version 7+
     *
     * @covers Monolog\Handler\ElasticaHandler::__construct
     * @covers Monolog\Handler\ElasticaHandler::handleBatch
     * @covers Monolog\Handler\ElasticaHandler::bulkSend
     * @covers Monolog\Handler\ElasticaHandler::getDefaultFormatter
     */
    public function testHandleIntegrationNewESVersion()
    {
        $msg = $this->getRecord(Level::Error, 'log', context: ['foo' => 7, 'bar', 'class' => new \stdClass], datetime: new \DateTimeImmutable("@0"));

        $expected = (array) $msg;
        $expected['datetime'] = $msg['datetime']->format(\DateTime::ISO8601);
        $expected['context'] = [
            'class' => '[object] (stdClass: {})',
            'foo' => 7,
            0 => 'bar',
        ];

        $clientOpts = ['url' => 'http://elastic:changeme@127.0.0.1:9200'];
        $client = new Client($clientOpts);

        $handler = new ElasticaHandler($client, $this->options);

        try {
            $handler->handleBatch([$msg]);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped("Cannot connect to Elastic Search server on localhost");
        }

        // check document id from ES server response
        $documentId = $this->getCreatedDocId($client->getLastResponse());
        $this->assertNotEmpty($documentId, 'No elastic document id received');

        // retrieve document source from ES and validate
        $document = $this->getDocSourceFromElastic(
            $client,
            $this->options['index'],
            null,
            $documentId
        );
        $this->assertEquals($expected, $document);

        // remove test index from ES
        $client->request("/{$this->options['index']}", Request::DELETE);
    }

    /**
     * Return last created document id from ES response
     * @param Response $response Elastica Response object
     */
    protected function getCreatedDocId(Response $response): ?string
    {
        $data = $response->getData();

        if (!empty($data['items'][0]['index']['_id'])) {
            return $data['items'][0]['index']['_id'];
        }

        var_dump('Unexpected response: ', $data);

        return null;
    }

    /**
     * Retrieve document by id from Elasticsearch
     * @param Client  $client Elastica client
     * @param ?string $type
     */
    protected function getDocSourceFromElastic(Client $client, string $index, $type, string $documentId): array
    {
        if ($type === null) {
            $path  = "/{$index}/_doc/{$documentId}";
        } else {
            $path  = "/{$index}/{$type}/{$documentId}";
        }
        $resp = $client->request($path, Request::GET);
        $data = $resp->getData();
        if (!empty($data['_source'])) {
            return $data['_source'];
        }

        return [];
    }
}
