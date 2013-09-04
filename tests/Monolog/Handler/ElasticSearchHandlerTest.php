<?php

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
use Monolog\TestCase;
use Monolog\Logger;
use Elastica\Client;
use Elastica\Request;
use Elastica\Response;

class ElasticSearchHandlerTest extends \PHPUnit_Framework_TestCase
{
    protected $client;

    public function setUp()
    {
        // Elastica lib required
        if (!class_exists("Elastica\Client")) {
            $this->markTestSkipped("ruflin/elastica not installed");
        }

        // base mock Elastica Client object
        $this->client = $this->getMockBuilder('Elastica\Client')
            ->setMethods(array('addDocuments'))
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @covers Monolog\Handler\ElasticSearchHandler::handleBatch
     */
    public function testHandleBatch()
    {
        // handler options
        $options = array(
            'index' => 'my_index',
            'type' => 'doc_type',
        );

        // log message
        $msg = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('foo' => 7, 'bar', 'class' => new \stdClass),
            'datetime' => new \DateTime("@0"),
            'extra' => array(),
            'message' => 'log',
        );

        // format expected result
        $formatter = new ElasticaFormatter($options['index'], $options['type']);
        $expected = $formatter->format($msg);

        // setup ES client mock
        $this->client->expects($this->once())
            ->method('addDocuments')
            ->with(array($expected));

        // perform test
        $handler = new ElasticSearchHandler($this->client, $options);
        $handler->handleBatch(array($msg));
    }

    /**
     * @covers Monolog\Handler\ElasticSearchHandler::setFormatter
     */
    public function testSetFormatter()
    {
        $handler = new ElasticSearchHandler($this->client);
        $formatter = new ElasticaFormatter('index', 'type');
        $handler->setFormatter($formatter);
        $this->assertInstanceOf('Monolog\Formatter\ElasticaFormatter', $handler->getFormatter());
    }

    /**
     * @covers                   Monolog\Handler\ElasticSearchHandler::setFormatter
     * @expectedException        RuntimeException
     * @expectedExceptionMessage ElasticSearchHandler is only compatible with ElasticaFormatter
     */
    public function testSetFormatterInvalid()
    {
        $handler = new ElasticSearchHandler($this->client);
        $formatter = new NormalizerFormatter();
        $handler->setFormatter($formatter);
    }

    /**
     * @covers            Monolog\Handler\ElasticSearchHandler::__construct
     * @expectedException Exception
     */
    public function testConstructorInvalid()
    {
        new ElasticSearchHandler(new \stdClass());
    }

    /**
     * @covers Monolog\Handler\ElasticSearchHandler::getOptions
     */
    public function testOptions()
    {
        $options = array(
            'index' => 'my_index',
            'type' => 'doc_type',
            'buffer_limit' => 100,
        );

        $expected = array(
            'index' => 'my_index',
            'type' => 'doc_type',
            'buffer_limit' => 100,
            'flush_overflow' => true,
            'ignore_error' => true,
        );

        $handler = new ElasticSearchHandler($this->client, $options);
        $this->assertEquals($expected, $handler->getOptions());
    }

    /**
     * Integration test using localhost Elastic Search server
     * @covers Monolog\Handler\ElasticSearchHandler::handleBatch
     */
    public function testHandleBatchIntegration()
    {
        $client = new Client();
        $options = array(
            'index' => 'phpunit_monolog',
            'type' => 'msg',
            'ignore_error' => false,
        );

        // build log message
        $msg = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('foo' => 7, 'bar', 'class' => new \stdClass),
            'datetime' => new \DateTime("@0"),
            'extra' => array(),
            'message' => 'log',
        );

        // expected values
        $expected = $msg;
        $expected['datetime'] = $msg['datetime']->format(\DateTime::ISO8601);
        $expected['context'] = array(
            'class' => '[object] (stdClass: {})',
            'foo' => 7,
            0 => 'bar',
        );

        // send log message
        $handler = new ElasticSearchHandler($client, $options);
        try {
            $handler->handleBatch(array($msg));
        } catch(\RuntimeException $e) {
            $this->markTestSkipped("Cannot connect to Elastic Search server on localhost");
        }

        // get auto id from ES server response
        $docId = $this->getCreatedDocId($client->getLastResponse());
        $this->assertNotEmpty($docId, 'No elastic document id received');

        // retrieve document and validate
        $resp = $client->request("/{$options['index']}/{$options['type']}/{$docId}");
        $data = $this->getResponseData($resp);

        // validation
        $this->assertEquals($expected, $data['_source']);

        // remove test index
        $client->request("/{$options['index']}", Request::DELETE);
    }

    /**
     * Return last created document id from ES response
     * @param Response $response Elastica Response object
     * @return string
     */
    protected function getCreatedDocId(Response $response)
    {
        $data = $this->getResponseData($response);
        if (!empty($data['items'][0]['create']['_id'])) {
            return $data['items'][0]['create']['_id'];
        }
    }

    /**
     * Return data from ES response
     * @param Response $response
     * @return array
     */
    protected function getResponseData(Response $response)
    {
        return $response->getData();
    }
}
