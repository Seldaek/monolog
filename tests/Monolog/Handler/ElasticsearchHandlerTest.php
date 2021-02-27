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

use Elasticsearch\ClientBuilder;
use Monolog\Formatter\ElasticsearchFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Test\TestCase;
use Monolog\Logger;
use Elasticsearch\Client;

class ElasticsearchHandlerTest extends TestCase
{
    /**
     * @var Client mock
     */
    protected $client;

    /**
     * @var array Default handler options
     */
    protected $options = [
        'index' => 'my_index',
        'type'  => 'doc_type',
    ];

    public function setUp(): void
    {
        // Elasticsearch lib required
        if (!class_exists('Elasticsearch\Client')) {
            $this->markTestSkipped('elasticsearch/elasticsearch not installed');
        }

        // base mock Elasticsearch Client object
        $this->client = $this->getMockBuilder('Elasticsearch\Client')
            ->onlyMethods(['bulk'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @covers Monolog\Handler\ElasticsearchHandler::write
     * @covers Monolog\Handler\ElasticsearchHandler::handleBatch
     * @covers Monolog\Handler\ElasticsearchHandler::bulkSend
     * @covers Monolog\Handler\ElasticsearchHandler::getDefaultFormatter
     */
    public function testHandle()
    {
        // log message
        $msg = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['foo' => 7, 'bar', 'class' => new \stdClass],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        // format expected result
        $formatter = new ElasticsearchFormatter($this->options['index'], $this->options['type']);
        $data = $formatter->format($msg);
        unset($data['_index'], $data['_type']);

        $expected = [
            'body' => [
                [
                    'index' => [
                        '_index' => $this->options['index'],
                        '_type' => $this->options['type'],
                    ],
                ],
                $data,
            ],
        ];

        // setup ES client mock
        $this->client->expects($this->any())
            ->method('bulk')
            ->with($expected);

        // perform tests
        $handler = new ElasticsearchHandler($this->client, $this->options);
        $handler->handle($msg);
        $handler->handleBatch([$msg]);
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
     * @covers                   Monolog\Handler\ElasticsearchHandler::setFormatter
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
        ];
        $handler = new ElasticsearchHandler($this->client, $this->options);
        $this->assertEquals($expected, $handler->getOptions());
    }

    /**
     * @covers       Monolog\Handler\ElasticsearchHandler::bulkSend
     * @dataProvider providerTestConnectionErrors
     */
    public function testConnectionErrors($ignore, $expectedError)
    {
        $hosts = [['host' => '127.0.0.1', 'port' => 1]];
        $client = ClientBuilder::create()
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

    /**
     * @return array
     */
    public function providerTestConnectionErrors()
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
    public function testHandleIntegration()
    {
        $msg = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['foo' => 7, 'bar', 'class' => new \stdClass],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        $expected = $msg;
        $expected['datetime'] = $msg['datetime']->format(\DateTime::ISO8601);
        $expected['context'] = [
            'class' => ["stdClass" => []],
            'foo' => 7,
            0 => 'bar',
        ];

        $hosts = [['host' => '127.0.0.1', 'port' => 9200]];
        $client = ClientBuilder::create()
            ->setHosts($hosts)
            ->build();
        $handler = new ElasticsearchHandler($client, $this->options);

        try {
            $handler->handleBatch([$msg]);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Cannot connect to Elasticsearch server on localhost');
        }

        // check document id from ES server response
        $documentId = $this->getCreatedDocId($client->transport->getLastConnection()->getLastRequestInfo());
        $this->assertNotEmpty($documentId, 'No elastic document id received');

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
     * @param  array       $info Elasticsearch last request info
     * @return string|null
     */
    protected function getCreatedDocId(array $info)
    {
        $data = json_decode($info['response']['body'], true);

        if (!empty($data['items'][0]['index']['_id'])) {
            return $data['items'][0]['index']['_id'];
        }
    }

    /**
     * Retrieve document by id from Elasticsearch
     *
     * @param  Client $client     Elasticsearch client
     * @param  string $index
     * @param  string $type
     * @param  string $documentId
     * @return array
     */
    protected function getDocSourceFromElastic(Client $client, $index, $type, $documentId)
    {
        $params = [
            'index' => $index,
            'type' => $type,
            'id' => $documentId,
        ];

        $data = $client->get($params);

        if (!empty($data['_source'])) {
            return $data['_source'];
        }

        return [];
    }
}
