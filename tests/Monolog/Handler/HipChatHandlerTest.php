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

/**
 * @author Rafael Dohms <rafael@doh.ms>
 * @see    https://www.hipchat.com/docs/api
 */
class HipChatHandlerTest extends TestCase
{
    private $res;
    /** @var  HipChatHandler */
    private $handler;

    public function testWriteV2()
    {
        $this->initHandlerAndSocket('myToken', 'room1', 'Monolog', false, 'hipchat.foo.bar', 'v2');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/POST \/v2\/room\/room1\/notification\?auth_token=.* HTTP\/1.1\\r\\nHost: hipchat.foo.bar\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    public function testWriteV2Notify()
    {
        $this->initHandlerAndSocket('myToken', 'room1', 'Monolog', true, 'hipchat.foo.bar', 'v2');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/POST \/v2\/room\/room1\/notification\?auth_token=.* HTTP\/1.1\\r\\nHost: hipchat.foo.bar\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    public function testRoomSpaces()
    {
        $this->initHandlerAndSocket('myToken', 'room name', 'Monolog', false, 'hipchat.foo.bar', 'v2');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/POST \/v2\/room\/room%20name\/notification\?auth_token=.* HTTP\/1.1\\r\\nHost: hipchat.foo.bar\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    /**
     * @depends testWriteHeader
     */
    public function testWriteContent($content)
    {
        $this->assertRegexp('/notify=0&message=test1&message_format=text&color=red&room_id=room1&from=Monolog$/', $content);
    }

    /**
     * @depends testWriteCustomHostHeader
     */
    public function testWriteContentNotify($content)
    {
        $this->assertRegexp('/notify=1&message=test1&message_format=text&color=red&room_id=room1&from=Monolog$/', $content);
    }

    /**
     * @depends testWriteV2
     */
    public function testWriteContentV2($content)
    {
        $this->assertRegexp('/notify=false&message=test1&message_format=text&color=red&from=Monolog$/', $content);
    }

    /**
     * @depends testWriteV2Notify
     */
    public function testWriteContentV2Notify($content)
    {
        $this->assertRegexp('/notify=true&message=test1&message_format=text&color=red&from=Monolog$/', $content);
    }

    public function testWriteContentV2WithoutName()
    {
        $this->initHandlerAndSocket('myToken', 'room1', null, false, 'hipchat.foo.bar', 'v2');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->assertRegexp('/notify=false&message=test1&message_format=text&color=red$/', $content);

        return $content;
    }

    public function testWriteWithComplexMessage()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Backup of database "example" finished in 16 minutes.'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/message=Backup\+of\+database\+%22example%22\+finished\+in\+16\+minutes\./', $content);
    }

    public function testWriteTruncatesLongMessage()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, str_repeat('abcde', 2000)));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/message='.str_repeat('abcde', 1900).'\+%5Btruncated%5D/', $content);
    }

    /**
     * @dataProvider provideLevelColors
     */
    public function testWriteWithErrorLevelsAndColors($level, $expectedColor)
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord($level, 'Backup of database "example" finished in 16 minutes.'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/color='.$expectedColor.'/', $content);
    }

    public function provideLevelColors()
    {
        return [
            [Logger::DEBUG,    'gray'],
            [Logger::INFO,     'green'],
            [Logger::WARNING,  'yellow'],
            [Logger::ERROR,    'red'],
            [Logger::CRITICAL, 'red'],
            [Logger::ALERT,    'red'],
            [Logger::EMERGENCY,'red'],
            [Logger::NOTICE,   'green'],
        ];
    }

    /**
     * @dataProvider provideBatchRecords
     */
    public function testHandleBatch($records, $expectedColor)
    {
        $this->initHandlerAndSocket();
        $this->handler->handleBatch($records);

        $content = $this->socket->getOutput();
        $this->assertRegexp('/color='.$expectedColor.'/', $content);
    }

    public function provideBatchRecords()
    {
        return [
            [
                [
                    ['level' => Logger::WARNING, 'message' => 'Oh bugger!', 'level_name' => 'warning', 'datetime' => new \DateTimeImmutable()],
                    ['level' => Logger::NOTICE, 'message' => 'Something noticeable happened.', 'level_name' => 'notice', 'datetime' => new \DateTimeImmutable()],
                    ['level' => Logger::CRITICAL, 'message' => 'Everything is broken!', 'level_name' => 'critical', 'datetime' => new \DateTimeImmutable()],
                ],
                'red',
            ],
            [
                [
                    ['level' => Logger::WARNING, 'message' => 'Oh bugger!', 'level_name' => 'warning', 'datetime' => new \DateTimeImmutable()],
                    ['level' => Logger::NOTICE, 'message' => 'Something noticeable happened.', 'level_name' => 'notice', 'datetime' => new \DateTimeImmutable()],
                ],
                'yellow',
            ],
            [
                [
                    ['level' => Logger::DEBUG, 'message' => 'Just debugging.', 'level_name' => 'debug', 'datetime' => new \DateTimeImmutable()],
                    ['level' => Logger::NOTICE, 'message' => 'Something noticeable happened.', 'level_name' => 'notice', 'datetime' => new \DateTimeImmutable()],
                ],
                'green',
            ],
            [
                [
                    ['level' => Logger::DEBUG, 'message' => 'Just debugging.', 'level_name' => 'debug', 'datetime' => new \DateTimeImmutable()],
                ],
                'gray',
            ],
        ];
    }

    public function testCreateWithTooLongNameV2()
    {
        // creating a handler with too long of a name but using the v2 api doesn't matter.
        $hipChatHandler = new HipChatHandler('token', 'room', 'SixteenCharsHere', false, Logger::CRITICAL, true, true, 'test', 'api.hipchat.com', 'v2');
    }

    private function initHandlerAndSocket($token = 'myToken', $room = 'room1', $name = 'Monolog', $notify = false, $host = 'api.hipchat.com', $version = 'v1')
    {
        $tmpFile = sys_get_temp_dir().'/monolog-test-socket.php';
        file_put_contents($tmpFile, <<<'SCRIPT'
<?php

$sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
socket_bind($sock, '127.0.0.1', 51984);
socket_listen($sock);

while (true) {
    $res = socket_accept($sock);
    socket_set_option($res, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 500));
    while ($read = socket_read($res, 1024)) {
        echo $read;
    }
    socket_close($res);
}
SCRIPT
);

        $this->socket = new \Symfony\Component\Process\Process(escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmpFile));
        $this->socket->start();

        $this->handler = new HipChatHandler($token, $room, $name, $notify, Logger::DEBUG, true, true, 'text', $host, $version);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');

        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    private function closeSocket()
    {
        $this->socket->stop();
    }

    public function tearDown()
    {
        if (isset($this->socket)) {
            $this->closeSocket();
            unset($this->socket);
        }
    }
}
