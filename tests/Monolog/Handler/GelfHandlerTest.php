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
use Gelf\IMessagePublisher;
use Gelf\MessagePublisher;
use Gelf\Message;

class MockMessagePublisher implements IMessagePublisher
{
    public function publish(Message $message) {
        $this->lastMessage = $message;
    }

    public $lastMessage = null;
}

/**
 * @covers Monolog\Handler\GelfHandler
 * @author Marc Abramowitz <marc@marc-abramowitz.com>
 */
class GelfHandlerTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists("Gelf\MessagePublisher"))
        {
            $this->markTestSkipped("gelf-php not installed");
        }
    }

    /**
     * @covers Monolog\Handler\GelfHandler::__construct
     */
    public function testConstruct()
    {
        $handler = new GelfHandler($this->getMessagePublisher());
        $this->assertInstanceOf('Monolog\Handler\GelfHandler', $handler);
    }

    protected function getHandler(IMessagePublisher $messagePublisher)
    {
        $handler = new GelfHandler($messagePublisher);
        $handler->setFormatter($this->getIdentityFormatter());
        return $handler;
    }

    protected function getMessagePublisher()
    {
        return new MockMessagePublisher('localhost');
    }

    public function testDebug()
    {
        $messagePublisher = $this->getMessagePublisher();
        $handler = $this->getHandler($messagePublisher);

        $record = $this->getRecord(Logger::DEBUG, "A test debug message");
        $handler->handle($record);

        $this->assertEquals(7, $messagePublisher->lastMessage->getLevel());
        $this->assertEquals('test', $messagePublisher->lastMessage->getFacility());
        $this->assertEquals($record['message'], $messagePublisher->lastMessage->getShortMessage());
        $this->assertEquals($record['message'], $messagePublisher->lastMessage->getFullMessage());
    }

    public function testWarning()
    {
        $messagePublisher = $this->getMessagePublisher();
        $handler = $this->getHandler($messagePublisher);

        $record = $this->getRecord(Logger::WARNING, "A test warning message");
        $handler->handle($record);

        $this->assertEquals(4, $messagePublisher->lastMessage->getLevel());
        $this->assertEquals('test', $messagePublisher->lastMessage->getFacility());
        $this->assertEquals($record['message'], $messagePublisher->lastMessage->getShortMessage());
        $this->assertEquals($record['message'], $messagePublisher->lastMessage->getFullMessage());
    }
}
