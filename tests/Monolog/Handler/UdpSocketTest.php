<?php

namespace Monolog\Handler;

use Monolog\TestCase;

class UdpSocketTest extends TestCase
{
    public function testWeDoNotSplitShortMessages()
    {
        $socket = $this->getMock('\Monolog\Handler\SyslogUdp\UdpSocket', array('send'), array('lol', 'lol'));

        $socket->expects($this->at(0))
            ->method('send')
            ->with("HEADER: The quick brown fox jumps over the lazy dog");

        $socket->write("The quick brown fox jumps over the lazy dog", "HEADER: ");
    }

    public function testWeSplitLongMessages()
    {
        $socket = $this->getMock('\Monolog\Handler\SyslogUdp\UdpSocket', array('send'), array('lol', 'lol'));

        $socket->expects($this->at(1))
            ->method('send')
            ->with("The quick brown fox jumps over the lazy dog");

        $aStringOfLength2048 = str_repeat("derp", 2048/4);

        $socket->write($aStringOfLength2048."The quick brown fox jumps over the lazy dog");
    }

    public function testAllSplitMessagesHasAHeader()
    {
        $socket = $this->getMock('\Monolog\Handler\SyslogUdp\UdpSocket', array('send'), array('lol', 'lol'));

        $socket->expects($this->exactly(5))
            ->method('send')
            ->with($this->stringStartsWith("HEADER"));

        $aStringOfLength8192 = str_repeat("derp", 2048);

        $socket->write($aStringOfLength8192, "HEADER");
    }
}
