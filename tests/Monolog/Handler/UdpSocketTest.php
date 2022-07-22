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

use Monolog\Handler\SyslogUdp\UdpSocket;
use Monolog\Test\TestCase;

/**
 * @requires extension sockets
 */
class UdpSocketTest extends TestCase
{
    public function testWeDoNotTruncateShortMessages()
    {
        $socket = $this->getMockBuilder('Monolog\Handler\SyslogUdp\UdpSocket')
            ->onlyMethods(['send'])
            ->setConstructorArgs(['lol'])
            ->getMock();

        $socket
            ->method('send')
            ->with("HEADER: The quick brown fox jumps over the lazy dog");

        $socket->write("The quick brown fox jumps over the lazy dog", "HEADER: ");
    }

    public function testLongMessagesAreTruncated()
    {
        $socket = $this->getMockBuilder('Monolog\Handler\SyslogUdp\UdpSocket')
            ->onlyMethods(['send'])
            ->setConstructorArgs(['lol'])
            ->getMock();

        $truncatedString = str_repeat("derp", 16254).'d';

        $socket->expects($this->exactly(1))
            ->method('send')
            ->with("HEADER" . $truncatedString);

        $longString = str_repeat("derp", 20000);

        $socket->write($longString, "HEADER");
    }

    public function testDoubleCloseDoesNotError()
    {
        $socket = new UdpSocket('127.0.0.1', 514);
        $socket->close();
        $socket->close();
    }

    public function testWriteAfterCloseReopened()
    {
        $socket = new UdpSocket('127.0.0.1', 514);
        $socket->close();
        $socket->write('foo', "HEADER");
    }
}
