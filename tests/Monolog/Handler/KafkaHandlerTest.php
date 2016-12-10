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

use Monolog\Test\TestCase;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class KafkaHandlerTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        if (!extension_loaded('rdkafka')) {
            $this->markTestSkipped('This test requires rdkafka extension to run');
        }
    }

    public function testConstruct()
    {
        $topic = $this->createPartialMock('RdKafka\\ProducerTopic', ['produce']);

        $producer = $this->createPartialMock('RdKafka\\Producer', ['newTopic']);
        $producer->expects($this->once())
            ->method('newTopic')
            ->with('test', $this->isInstanceOf('RdKafka\\TopicConf'))
            ->willReturn($topic);

        $handler = new KafkaHandler($producer, 'test');
        $this->assertInstanceOf('Monolog\Handler\KafkaHandler', $handler);
    }

    public function testConstructWithTopicConfig()
    {
        $topic = $this->createPartialMock('RdKafka\\ProducerTopic', ['produce']);

        $topicConfig = $this->createPartialMock('RdKafka\\TopicConf', []);

        $producer = $this->createPartialMock('RdKafka\\Producer', ['newTopic']);
        $producer->expects($this->once())
            ->method('newTopic')
            ->with('test', $topicConfig)
            ->willReturn($topic);

        $handler = new KafkaHandler($producer, 'test', $topicConfig);
        $this->assertInstanceOf('Monolog\Handler\KafkaHandler', $handler);
    }

    public function testTopicGetter()
    {
        $topic = $this->createPartialMock('RdKafka\\ProducerTopic', ['produce']);

        $producer = $this->createPartialMock('RdKafka\\Producer', ['newTopic']);
        $producer->expects($this->once())
            ->method('newTopic')
            ->with('test', $this->isInstanceOf('RdKafka\\TopicConf'))
            ->willReturn($topic);

        $handler = new KafkaHandler($producer, 'test');
        $this->assertEquals($handler->getTopic(), $topic);
    }

    public function testShouldLogMessage()
    {
        $record = $this->getRecord();
        $expectedMessage = sprintf("[%s] test.WARNING: test [] []", $record['datetime']);

        $topic = $this->createPartialMock('RdKafka\\ProducerTopic', ['produce']);
        $topic->expects($this->once())
            ->method('produce')
            ->with(RD_KAFKA_PARTITION_UA, 0, $expectedMessage);

        $producer = $this->createPartialMock('RdKafka\\Producer', ['newTopic']);
        $producer->expects($this->once())
            ->method('newTopic')
            ->with('test', $this->isInstanceOf('RdKafka\\TopicConf'))
            ->willReturn($topic);

        $handler = new KafkaHandler($producer, 'test');
        $handler->handle($record);
    }

    public function testShouldNotTrimTrailingNewlinesWhenOptionDisabled()
    {
        $record = $this->getRecord();
        $expectedMessage = sprintf("[%s] test.WARNING: test [] []\n", $record['datetime']);

        $topic = $this->createPartialMock('RdKafka\\ProducerTopic', ['produce']);
        $topic->expects($this->once())
            ->method('produce')
            ->with(RD_KAFKA_PARTITION_UA, 0, $expectedMessage);

        $producer = $this->createPartialMock('RdKafka\\Producer', ['newTopic']);
        $producer->expects($this->once())
            ->method('newTopic')
            ->with('test', $this->isInstanceOf('RdKafka\\TopicConf'))
            ->willReturn($topic);

        $handler = new KafkaHandler($producer, 'test', null, Logger::WARNING, true, false);
        $handler->setFormatter(new LineFormatter());

        $handler->handle($record);
    }
}
