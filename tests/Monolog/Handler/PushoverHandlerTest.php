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
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

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
        $this->createHandler('myToken', 'myUser', 'Backup finished - SQL1');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/title=Backup\+finished\+-\+SQL1/', $content);
    }

    public function testWriteWithComplexMessage()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Backup of database "example" finished in 16 minutes.'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/message=Backup\+of\+database\+%22example%22\+finished\+in\+16\+minutes\./', $content);
    }

    public function testWriteWithTooLongMessage()
    {
        $message = str_pad('test', 520, 'a');
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, $message));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $expectedMessage = substr($message, 0, 505);

        $this->assertRegexp('/message=' . $expectedMessage . '&title/', $content);
    }

    private function createHandler($token = 'myToken', $user = 'myUser', $title = 'Monolog')
    {
        $constructorArgs = array($token, $user, $title);
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMock(
            '\Monolog\Handler\PushoverHandler',
            array('fsockopen', 'streamSetTimeout'),
            $constructorArgs
        );

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->will($this->returnValue($this->res));
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->will($this->returnValue(true));

        $this->handler->setFormatter($this->getIdentityFormatter());
    }
}
