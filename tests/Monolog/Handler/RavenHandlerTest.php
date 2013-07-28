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

use Monolog\TestCase;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;

class RavenHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists("Raven_Client")) {
            $this->markTestSkipped("raven/raven not installed");
        }

        require_once __DIR__ . '/MockRavenClient.php';
    }

    /**
     * @covers Monolog\Handler\RavenHandler::__construct
     */
    public function testConstruct()
    {
        $handler = new RavenHandler($this->getRavenClient());
        $this->assertInstanceOf('Monolog\Handler\RavenHandler', $handler);
    }

    protected function getHandler($ravenClient)
    {
        $handler = new RavenHandler($ravenClient);

        return $handler;
    }

    protected function getRavenClient()
    {
        $dsn = 'http://43f6017361224d098402974103bfc53d:a6a0538fc2934ba2bed32e08741b2cd3@marca.python.live.cheggnet.com:9000/1';

        return new MockRavenClient($dsn);
    }

    public function testDebug()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $record = $this->getRecord(Logger::DEBUG, "A test debug message");
        $handler->handle($record);

        $this->assertEquals($ravenClient::DEBUG, $ravenClient->lastData['level']);
        $this->assertContains($record['message'], $ravenClient->lastData['message']);
    }

    public function testWarning()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $record = $this->getRecord(Logger::WARNING, "A test warning message");
        $handler->handle($record);

        $this->assertEquals($ravenClient::WARNING, $ravenClient->lastData['level']);
        $this->assertContains($record['message'], $ravenClient->lastData['message']);
    }

    public function testException()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        try {
            $this->methodThatThrowsAnException();
        } catch (\Exception $e) {
            $record = $this->getRecord(Logger::ERROR, $e->getMessage(), array('exception' => $e));
            $handler->handle($record);
        }

        $this->assertEquals($record['message'], $ravenClient->lastData['message']);
    }

    public function testHandleBatch()
    {
        $records = $this->getMultipleRecords();

        $logFormatter = $this->getMock('Monolog\\Formatter\\FormatterInterface');
        $logFormatter->expects($this->once())->method('formatBatch');

        $formatter = $this->getMock('Monolog\\Formatter\\FormatterInterface');
        $formatter->expects($this->once())->method('format');

        $handler = $this->getHandler($this->getRavenClient());
        $handler->setBatchFormatter($logFormatter);
        $handler->setFormatter($formatter);
        $handler->handleBatch($records);
    }

    public function testHandleBatchDoNothingIfRecordsAreBelowLevel()
    {
        $records = array(
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information'),
        );

        $handler = $this->getMock('Monolog\Handler\RavenHandler', null, array($this->getRavenClient()));
        $handler->expects($this->never())->method('handle');
        $handler->setLevel(Logger::ERROR);
        $handler->handleBatch($records);
    }

    public function testGetSetBatchFormatter()
    {
        $ravenClient = $this->getRavenClient();
        $handler = $this->getHandler($ravenClient);

        $handler->setBatchFormatter($formatter = new LineFormatter());
        $this->assertSame($formatter, $handler->getBatchFormatter());
    }

    private function methodThatThrowsAnException()
    {
        throw new \Exception('This is an exception');
    }
}
