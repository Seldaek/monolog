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

use Monolog\Formatter\FlowdockFormatter;
use Monolog\Test\TestCase;
use Monolog\Logger;

/**
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 * @see    https://www.hipchat.com/docs/api
 */
class FlowdockHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    /**
     * @var FlowdockHandler
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
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->closeSocket();

        $this->assertRegexp('/POST \/v1\/messages\/team_inbox\/.* HTTP\/1.1\\r\\nHost: api.flowdock.com\\r\\nContent-Type: application\/json\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    /**
     * @depends testWriteHeader
     */
    public function testWriteContent(string $content)
    {
        $this->assertRegexp('/"source":"test_source"/', $content);
        $this->assertRegexp('/"from_address":"source@test\.com"/', $content);
    }


    private function initHandlerAndSocket($token = 'myToken')
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

        $this->handler = new FlowdockHandler($token);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');

        $this->handler->setFormatter(new FlowdockFormatter('test_source', 'source@test.com'));
    }

    private function closeSocket()
    {
        $this->socket->stop();

        return $this->socket->getOutput();
    }

    public function tearDown()
    {
        if (isset($this->socket)) {
            $this->closeSocket();
            unset($this->socket);
        }
    }
}
