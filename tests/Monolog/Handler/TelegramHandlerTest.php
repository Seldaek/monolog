<?php

declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Test\TestCase;

/**
 * @author Rafael Mello <merorafael@gmail.com>
 *
 * @see    https://core.telegram.org/bots/api
 */
class TelegramHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    /**
     * @var TelegramHandler
     */
    private $handler;

    public function setUp()
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl to run');
        }
    }

    public function testWriteHeader()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('{POST /botmyToken/sendMessage HTTP/1.1\\r\\nHost: api.telegram.org\\r\\nContent-Type: application/json\\r\\nContent-Length: 35\\r\\n\\r\\n}', $content);
    }

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/{"chat_id":"myChat","text":"test1"}$/', $content);
    }

    private function createHandler($token = 'myToken', $chatId = 'myChat')
    {
        $constructorArgs = [$token, $chatId, Logger::DEBUG, true];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder('Monolog\Handler\TelegramHandler')
            ->setConstructorArgs($constructorArgs)
            ->setMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->will($this->returnValue($this->res));
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->will($this->returnValue(true));
        $this->handler->expects($this->any())
            ->method('closeSocket')
            ->will($this->returnValue(true));

        $this->handler->setFormatter($this->getIdentityFormatter());
    }
}
