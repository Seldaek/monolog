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

class ElasticSearchHandlerTest extends \PHPUnit_Framework_TestCase
{
    protected $esClient;

    public function setUp()
    {
        // Elastica lib required
        if (!class_exists("Elastica\Client")) {
            $this->markTestSkipped("ruflin/elastica not installed");
        }

        // base mock Elastica Client object
        $this->esClient = $this->getMockBuilder('Elastica\Client')
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
        $this->esClient->expects($this->once())
            ->method('addDocuments')
            ->with(array($expected));

        // perform test
        $esh = new ElasticSearchHandler($this->esClient, $options);
        $esh->handleBatch(array($msg));
    }

    /**
     * @covers Monolog\Handler\ElasticSearchHandler::setFormatter
     */
    public function testSetFormatter()
    {
        $esh = new ElasticSearchHandler($this->esClient);
        $formatter = new ElasticaFormatter('index', 'type');
        $esh->setFormatter($formatter);
        $this->assertInstanceOf('Monolog\Formatter\ElasticaFormatter', $esh->getFormatter());
    }

    /**
     * @covers                   Monolog\Handler\ElasticSearchHandler::setFormatter
     * @expectedException        RuntimeException
     * @expectedExceptionMessage ElasticSearchHandler is only compatible with ElasticaFormatter
     */
    public function testSetFormatterInvalid()
    {
        $esh = new ElasticSearchHandler($this->esClient);
        $formatter = new NormalizerFormatter();
        $esh->setFormatter($formatter);
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

        $esh = new ElasticSearchHandler($this->esClient, $options);
        $this->assertEquals($expected, $esh->getOptions());
    }

    /**
     * Integration test using localhost Elastic Search server
     * @covers Monolog\Handler\ElasticSearchHandler::handleBatch
     */
    public function testHandleBatchIntegration()
    {
        $esClient = new \Elastica\Client();
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
        $expected['datetime'] = $msg['datetime']->format('c');
        $expected['context'] = array(
            'class' => '[object] (stdClass: {})',
            'foo' => 7,
            0 => 'bar',
        );

        // send log message
        $esh = new ElasticSearchHandler($esClient, $options);
        try {
            $esh->handleBatch(array($msg));
        } catch(\RuntimeException $e) {
            $this->markTestSkipped("Cannot connect to Elastic Search server on localhost");
        }

        // get auto id from ES server response
        $docId = $this->getCreatedDocId($esClient->getLastResponse());
        $this->assertNotEmpty($docId, 'No elastic document id received');

        // retrieve document and validate
        $resp = $esClient->request("/{$options['index']}/{$options['type']}/{$docId}");
        $data = $this->getResponseData($resp);

        // validation
        $this->assertEquals($expected, $data['_source']);

        // remove test index
        $esClient->request("/{$options['index']}", \Elastica\Request::DELETE);
    }

    /**
     * Return last created document id from ES response
     * @param Elastica\Response $response
     * @return string
     */
    protected function getCreatedDocId(\Elastica\Response $response)
    {
        $data = $this->getResponseData($response);
        if (!empty($data['items'][0]['create']['_id'])) {
            return $data['items'][0]['create']['_id'];
        }
    }

    /**
     * Return data from ES response
     * @param \Elastica\Response $response
     * @return array
     */
    protected function getResponseData(\Elastica\Response $response)
    {
        return $response->getData();
    }
}
