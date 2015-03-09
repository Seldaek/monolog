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
 * @author Rafael Dohms <rafael@doh.ms>
 * @see    https://www.hipchat.com/docs/api
 */
class HipChatHandlerTest extends TestCase
{
    private $res;
    private $handler;

    public function testWriteHeader()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/POST \/v1\/rooms\/message\?format=json&auth_token=.* HTTP\/1.1\\r\\nHost: api.hipchat.com\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    public function testWriteCustomHostHeader()
    {
        $this->createHandler('myToken', 'room1', 'Monolog', false, 'hipchat.foo.bar');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/POST \/v1\/rooms\/message\?format=json&auth_token=.* HTTP\/1.1\\r\\nHost: hipchat.foo.bar\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    /**
     * @depends testWriteHeader
     */
    public function testWriteContent($content)
    {
        $this->assertRegexp('/from=Monolog&room_id=room1&notify=0&message=test1&message_format=text&color=red$/', $content);
    }

    public function testWriteWithComplexMessage()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Backup of database "example" finished in 16 minutes.'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/message=Backup\+of\+database\+%22example%22\+finished\+in\+16\+minutes\./', $content);
    }

    /**
     * @dataProvider provideLevelColors
     */
    public function testWriteWithErrorLevelsAndColors($level, $expectedColor)
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord($level, 'Backup of database "example" finished in 16 minutes.'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/color='.$expectedColor.'/', $content);
    }

    public function provideLevelColors()
    {
        return array(
            array(Logger::DEBUG,    'gray'),
            array(Logger::INFO,     'green'),
            array(Logger::WARNING,  'yellow'),
            array(Logger::ERROR,    'red'),
            array(Logger::CRITICAL, 'red'),
            array(Logger::ALERT,    'red'),
            array(Logger::EMERGENCY,'red'),
            array(Logger::NOTICE,   'green'),
        );
    }

    /**
     * @dataProvider provideBatchRecords
     */
    public function testHandleBatch($records, $expectedColor)
    {
        $this->createHandler();

        $this->handler->handleBatch($records);

        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/color='.$expectedColor.'/', $content);
    }

    public function provideBatchRecords()
    {
        return array(
            array(
                array(
                    array('level' => Logger::WARNING, 'message' => 'Oh bugger!', 'level_name' => 'warning', 'datetime' => new \DateTime()),
                    array('level' => Logger::NOTICE, 'message' => 'Something noticeable happened.', 'level_name' => 'notice', 'datetime' => new \DateTime()),
                    array('level' => Logger::CRITICAL, 'message' => 'Everything is broken!', 'level_name' => 'critical', 'datetime' => new \DateTime())
                ),
                'red',
            ),
            array(
                array(
                    array('level' => Logger::WARNING, 'message' => 'Oh bugger!', 'level_name' => 'warning', 'datetime' => new \DateTime()),
                    array('level' => Logger::NOTICE, 'message' => 'Something noticeable happened.', 'level_name' => 'notice', 'datetime' => new \DateTime()),
                ),
                'yellow',
            ),
            array(
                array(
                    array('level' => Logger::DEBUG, 'message' => 'Just debugging.', 'level_name' => 'debug', 'datetime' => new \DateTime()),
                    array('level' => Logger::NOTICE, 'message' => 'Something noticeable happened.', 'level_name' => 'notice', 'datetime' => new \DateTime()),
                ),
                'green',
            ),
            array(
                array(
                    array('level' => Logger::DEBUG, 'message' => 'Just debugging.', 'level_name' => 'debug', 'datetime' => new \DateTime()),
                ),
                'gray',
            ),
        );
    }

    private function createHandler($token = 'myToken', $room = 'room1', $name = 'Monolog', $notify = false, $host = 'api.hipchat.com')
    {
        $constructorArgs = array($token, $room, $name, $notify, Logger::DEBUG, true, true, 'text', $host);
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMock(
            '\Monolog\Handler\HipChatHandler',
            array('fsockopen', 'streamSetTimeout', 'closeSocket'),
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
        $this->handler->expects($this->any())
            ->method('closeSocket')
            ->will($this->returnValue(true));

        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateWithTooLongName()
    {
        $hipChatHandler = new \Monolog\Handler\HipChatHandler('token', 'room', 'SixteenCharsHere');
    }
}
