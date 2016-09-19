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
use Monolog\Handler\SyslogUdp\UdpSocket;

/**
 * @requires extension sockets
 */
class UdpSocketTest extends TestCase
{
    public function testWeDoNotTruncateShortMessages()
    {
        $this->initSocket();

        $socket = new UdpSocket('127.0.0.1', 51984);
        $socket->write("The quick brown fox jumps over the lazy dog", "HEADER: ");

        $this->closeSocket();
        $this->assertEquals('HEADER: The quick brown fox jumps over the lazy dog', $this->socket->getOutput());
    }

    public function testLongMessagesAreTruncated()
    {
        $this->initSocket();

        $socket = new UdpSocket('127.0.0.1', 51984);

        $longString = str_repeat("derp", 20000);
        $socket->write($longString, "HEADER");

        $truncatedString = str_repeat("derp", 16254).'d';

        $this->closeSocket();
        $this->assertEquals('HEADER'.$truncatedString, $this->socket->getOutput());
    }

    public function testDoubleCloseDoesNotError()
    {
        $socket = new UdpSocket('127.0.0.1', 514);
        $socket->close();
        $socket->close();
    }

    /**
     * @expectedException LogicException
     */
    public function testWriteAfterCloseErrors()
    {
        $socket = new UdpSocket('127.0.0.1', 514);
        $socket->close();
        $socket->write('foo', "HEADER");
    }

    private function initSocket()
    {
        $tmpFile = sys_get_temp_dir().'/monolog-test-socket.php';
        file_put_contents($tmpFile, <<<'SCRIPT'
<?php

$sock = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
socket_bind($sock, '127.0.0.1', 51984);
echo 'INIT';
while (true) {
    socket_recvfrom($sock, $read, 100*1024, 0, $ip, $port);
    echo $read;
}
SCRIPT
);

        $this->socket = new \Symfony\Component\Process\Process(escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmpFile));
        $this->socket->start();
        while (true) {
            if ($this->socket->getOutput() === 'INIT') {
                $this->socket->clearOutput();
                break;
            }
            usleep(100);
        }
    }

    private function closeSocket()
    {
        usleep(100);
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
