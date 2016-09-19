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
use Monolog\Util\LocalSocket;

/**
 * @requires extension sockets
 */
class UdpSocketTest extends TestCase
{
    public function testWeDoNotTruncateShortMessages()
    {
        $this->initSocket();

        $socket = new UdpSocket('127.0.0.1', 51983);
        $socket->write("The quick brown fox jumps over the lazy dog", "HEADER: ");

        $this->assertEquals('HEADER: The quick brown fox jumps over the lazy dog', $this->socket->getOutput());
    }

    public function testLongMessagesAreTruncated()
    {
        $this->initSocket();

        $socket = new UdpSocket('127.0.0.1', 51983);

        $longString = str_repeat("derp", 20000);
        $socket->write($longString, "HEADER");

        $truncatedString = str_repeat("derp", 16254).'d';
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
        $this->socket = LocalSocket::initSocket(51983, LocalSocket::UDP);
    }

    public function tearDown()
    {
        unset($this->socket, $this->handler);
    }
}
