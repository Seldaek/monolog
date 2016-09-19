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
 * @author Robert Kaufmann III <rok3@rok3.me>
 */
class LogEntriesHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    /**
     * @var LogEntriesHandler
     */
    private $handler;

    public function testWriteContent()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Critical write test'));

        $content = $this->closeSocket();
        $this->assertRegexp('/testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] test.CRITICAL: Critical write test/', $content);
    }

    public function testWriteBatchContent()
    {
        $records = [
            $this->getRecord(),
            $this->getRecord(),
            $this->getRecord(),
        ];
        $this->initHandlerAndSocket();
        $this->handler->handleBatch($records);

        $content = $this->closeSocket();
        $this->assertRegexp('/(testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] .* \[\] \[\]\n){3}/', $content);
    }

    private function initHandlerAndSocket()
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

        $useSSL = extension_loaded('openssl');
        $this->handler = new LogEntriesHandler('testToken', $useSSL, Logger::DEBUG, true);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');
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
