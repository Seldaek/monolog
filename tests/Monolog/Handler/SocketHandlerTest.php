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
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */
class SocketHandlerTest extends TestCase
{
    /**
     * @var Monolog\Handler\SocketHandler
     */
    private $handler;

    /**
     * @var resource
     */
    private $res;

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidHostname()
    {
        $this->createHandler('garbage://here');
        $this->writeRecord('data');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadConnectionTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setConnectionTimeout(-1);
    }

    public function testSetConnectionTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setConnectionTimeout(10.1);
        $this->assertEquals(10.1, $this->handler->getConnectionTimeout());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setTimeout(-1);
    }

    public function testSetTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setTimeout(10.25);
        $this->assertEquals(10.25, $this->handler->getTimeout());
    }

    public function testSetWritingTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setWritingTimeout(10.25);
        $this->assertEquals(10.25, $this->handler->getWritingTimeout());
    }

    public function testSetConnectionString()
    {
        $this->createHandler('tcp://localhost:9090');
        $this->assertEquals('tcp://localhost:9090', $this->handler->getConnectionString());
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testExceptionIsThrownOnFsockopenError()
    {
        $this->createHandler('tcp://127.0.0.1:51985');

        $this->writeRecord('Hello world');
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testExceptionIsThrownOnPfsockopenError()
    {
        $this->createHandler('tcp://127.0.0.1:51985');
        $this->handler->setPersistent(true);

        $this->writeRecord('Hello world');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testWriteFailsOnIfFwriteReturnsFalse()
    {
        $this->initHandlerAndSocket();
        $this->writeRecord('Hello world');

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'resource');
        $reflectionProperty->setAccessible(true);
        fclose($reflectionProperty->getValue($this->handler));

        $this->writeRecord('Hello world');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testWriteFailsOnIncompleteWrite()
    {
        $this->initHandlerAndSocket();

        $this->handler->setWritingTimeout(1);

        // the socket will close itself after processing 10000 bytes so while processing b, and then c write fails
        $this->writeRecord(str_repeat("aaaaaaaaaa\n", 700));
        $this->assertTrue(true); // asserting to make sure we reach this point
        $this->writeRecord(str_repeat("bbbbbbbbbb\n", 700));
        $this->assertTrue(true); // asserting to make sure we reach this point
        $this->writeRecord(str_repeat("cccccccccc\n", 700));
        $this->fail('The test should not reach here');
    }

    public function testWriteWithMemoryFile()
    {
        $this->initHandlerAndSocket();
        $this->writeRecord('test1');
        $this->writeRecord('test2');
        $this->writeRecord('test3');
        $this->closeSocket();
        $this->assertEquals('test1test2test3', $this->socket->getOutput());
    }

    public function testClose()
    {
        $this->initHandlerAndSocket();
        $this->writeRecord('Hello world');

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'resource');
        $reflectionProperty->setAccessible(true);

        $this->assertInternalType('resource', $reflectionProperty->getValue($this->handler));
        $this->handler->close();
        $this->assertFalse(is_resource($reflectionProperty->getValue($this->handler)), "Expected resource to be closed after closing handler");
    }

    public function testCloseDoesNotClosePersistentSocket()
    {
        $this->initHandlerAndSocket();
        $this->handler->setPersistent(true);
        $this->writeRecord('Hello world');

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'resource');
        $reflectionProperty->setAccessible(true);

        $this->assertTrue(is_resource($reflectionProperty->getValue($this->handler)));
        $this->handler->close();
        $this->assertTrue(is_resource($reflectionProperty->getValue($this->handler)));
    }

    private function createHandler($connectionString)
    {
        $this->handler = new SocketHandler($connectionString);
        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    private function writeRecord($string)
    {
        $this->handler->handle($this->getRecord(Logger::WARNING, $string));
    }

    private function initHandlerAndSocket()
    {
        $tmpFile = sys_get_temp_dir().'/monolog-test-socket.php';
        file_put_contents($tmpFile, <<<'SCRIPT'
<?php

$sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
socket_bind($sock, '127.0.0.1', 51984);
socket_listen($sock);
$res = socket_accept($sock);
socket_set_option($res, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 500));
$bytesRead = 0;
while ($read = socket_read($res, 1024)) {
    echo $read;
    $bytesRead += strlen($read);
    if ($bytesRead > 10000) {
        socket_close($res);
        socket_close($sock);
        die('CLOSED');
    }
}
echo 'EXIT';
socket_close($res);
SCRIPT
);

        $this->socket = new \Symfony\Component\Process\Process(escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmpFile));
        $this->socket->start();

        $this->handler = new SocketHandler('tcp://127.0.0.1:51984');
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
