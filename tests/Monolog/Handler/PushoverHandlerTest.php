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
use Monolog\Util\LocalSocket;

/**
 * Almost all examples (expected header, titles, messages) taken from
 * https://www.pushover.net/api
 * @author Sebastian Göttschkes <sebastian.goettschkes@googlemail.com>
 * @see https://www.pushover.net/api
 */
class PushoverHandlerTest extends TestCase
{
    private $res;
    private $handler;

    public function testWriteHeader()
    {
        $this->initHandlerAndSocket();
        $this->handler->setHighPriorityLevel(Logger::EMERGENCY); // skip priority notifications
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/POST \/1\/messages.json HTTP\/1.1\\r\\nHost: api.pushover.net\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    /**
     * @depends testWriteHeader
     */
    public function testWriteContent($content)
    {
        $this->assertRegexp('/token=myToken&user=myUser&message=test1&title=Monolog&timestamp=\d{10}$/', $content);
    }

    public function testWriteWithComplexTitle()
    {
        $this->initHandlerAndSocket('myToken', 'myUser', 'Backup finished - SQL1');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/title=Backup\+finished\+-\+SQL1/', $content);
    }

    public function testWriteWithComplexMessage()
    {
        $this->initHandlerAndSocket();
        $this->handler->setHighPriorityLevel(Logger::EMERGENCY); // skip priority notifications
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Backup of database "example" finished in 16 minutes.'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/message=Backup\+of\+database\+%22example%22\+finished\+in\+16\+minutes\./', $content);
    }

    public function testWriteWithTooLongMessage()
    {
        $message = str_pad('test', 520, 'a');
        $this->initHandlerAndSocket();
        $this->handler->setHighPriorityLevel(Logger::EMERGENCY); // skip priority notifications
        $this->handler->handle($this->getRecord(Logger::CRITICAL, $message));
        $content = $this->socket->getOutput();

        $expectedMessage = substr($message, 0, 505);

        $this->assertRegexp('/message=' . $expectedMessage . '&title/', $content);
    }

    public function testWriteWithHighPriority()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/token=myToken&user=myUser&message=test1&title=Monolog&timestamp=\d{10}&priority=1$/', $content);
    }

    public function testWriteWithEmergencyPriority()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::EMERGENCY, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/token=myToken&user=myUser&message=test1&title=Monolog&timestamp=\d{10}&priority=2&retry=30&expire=25200$/', $content);
    }

    public function testWriteToMultipleUsers()
    {
        $this->markTestIncomplete('LocalSocket buffer does not support multiple-connections');

        $this->initHandlerAndSocket('myToken', ['userA', 'userB']);
        $this->handler->handle($this->getRecord(Logger::EMERGENCY, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/token=myToken&user=userA&message=test1&title=Monolog&timestamp=\d{10}&priority=2&retry=30&expire=25200POST/', $content);
        $this->assertRegexp('/token=myToken&user=userB&message=test1&title=Monolog&timestamp=\d{10}&priority=2&retry=30&expire=25200$/', $content);
    }

    private function initHandlerAndSocket($token = 'myToken', $user = 'myUser', $title = 'Monolog')
    {
        $this->socket = LocalSocket::initSocket();
        $this->handler = new PushoverHandler($token, $user, $title);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');

        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    public function tearDown()
    {
        unset($this->socket, $this->handler);
    }
}
