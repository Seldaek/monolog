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

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Formatter\LineFormatter;

class GooglePubSubHandlerTest extends \Monolog\Test\MonologTestCase
{
    private PubSubClient $pubSubClient;
    private Topic $topic;

    protected function setUp(): void
    {
        $this->pubSubClient = $this->createMock(PubSubClient::class);
        $this->topic = $this->createMock(Topic::class);
        
        $this->pubSubClient
            ->expects($this->any())
            ->method('topic')
            ->willReturn($this->topic);
    }

    public function testConstruct()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $this->assertInstanceOf(GooglePubSubHandler::class, $handler);
        $this->assertEquals('test-topic', $handler->getTopicName());
        $this->assertEquals([], $handler->getAttributes());
    }

    public function testConstructWithAttributes()
    {
        $attributes = ['service' => 'test-service', 'environment' => 'test'];
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic', $attributes);
        $this->assertEquals($attributes, $handler->getAttributes());
    }

    public function testWrite()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $handler->setFormatter(new LineFormatter());

        $record = $this->getRecord(Level::Warning, 'test message', ['context' => 'data']);

        $this->topic
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($message) {
                return $message['data'] !== '' &&
                       $message['attributes']['log_level'] === 'WARNING' &&
                       $message['attributes']['channel'] === 'test' &&
                       isset($message['attributes']['datetime']) &&
                       $message['attributes']['context'] === '{"context":"data"}';
            }));

        $handler->handle($record);
    }

    public function testWriteWithCustomAttributes()
    {
        $attributes = ['service' => 'test-service'];
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic', $attributes);
        $handler->setFormatter(new LineFormatter());

        $record = $this->getRecord(Level::Info, 'test message');

        $this->topic
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($message) {
                return $message['data'] !== '' &&
                       $message['attributes']['service'] === 'test-service' &&
                       $message['attributes']['log_level'] === 'INFO';
            }));

        $handler->handle($record);
    }

    public function testWriteWithLargeMessage()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $handler->setFormatter(new LineFormatter());

        // Create a message larger than MAX_MESSAGE_SIZE (10MB)
        $largeMessage = str_repeat('a', 10485760 + 1000); // 10MB + 1KB
        $record = $this->getRecord(Level::Error, $largeMessage);
        $record->formatted = $largeMessage;

        $this->topic
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($message) {
                return strlen($message['data']) === 1048576 && // 1MB
                       $message['attributes']['truncated'] === 'true' &&
                       isset($message['attributes']['original_size']);
            }));

        $handler->handle($record);
    }

    public function testWriteWithException()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $handler->setFormatter(new LineFormatter());

        $record = $this->getRecord(Level::Error, 'test message');
        $record->formatted = 'formatted message';

        $this->topic
            ->expects($this->once())
            ->method('publish')
            ->willThrowException(new \Exception('Publish failed'));

        // Should not throw exception, but log error
        $handler->handle($record);
    }

    public function testWriteWithInvalidFormattedRecord()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');

        $record = $this->getRecord(Level::Error, 'test message');
        // Don't set formatted property

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('GooglePubSubHandler accepts only formatted records as a string');

        // Use reflection to call the protected write method
        $reflection = new \ReflectionClass($handler);
        $writeMethod = $reflection->getMethod('write');
        $writeMethod->setAccessible(true);
        $writeMethod->invoke($handler, $record);
    }

    public function testSetAttributes()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $newAttributes = ['new' => 'attribute'];
        
        $handler->setAttributes($newAttributes);
        $this->assertEquals($newAttributes, $handler->getAttributes());
    }

    public function testWriteWithExtraData()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $handler->setFormatter(new LineFormatter());

        $record = $this->getRecord(Level::Info, 'test message');
        $record->formatted = 'formatted message';
        $record->extra = ['extra_key' => 'extra_value'];

        $this->topic
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($message) {
                return $message['attributes']['extra'] === '{"extra_key":"extra_value"}';
            }));

        $handler->handle($record);
    }

    public function testWriteWithContextAndExtra()
    {
        $handler = new GooglePubSubHandler($this->pubSubClient, 'test-topic');
        $handler->setFormatter(new LineFormatter());

        $record = $this->getRecord(Level::Info, 'test message', ['context_key' => 'context_value']);
        $record->formatted = 'formatted message';
        $record->extra = ['extra_key' => 'extra_value'];

        $this->topic
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($message) {
                return $message['attributes']['context'] === '{"context_key":"context_value"}' &&
                       $message['attributes']['extra'] === '{"extra_key":"extra_value"}';
            }));

        $handler->handle($record);
    }
}
