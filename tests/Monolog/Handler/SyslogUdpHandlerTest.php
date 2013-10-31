<?php

namespace Monolog\Handler;

use Monolog\TestCase;

class SyslogUdpHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testWeValidateFacilities()
    {
        $handler = new SyslogUdpHandler("loiwjefoiwjef", "ip");
    }

    public function testWeSplitIntoLines()
    {
        $handler = new SyslogUdpHandler("local5", "127.0.0.1");
        $handler->setFormatter(new \Monolog\Formatter\ChromePHPFormatter());

        $socket = $this->getMock('\Monolog\Handler\SyslogUdp\UdpSocket', ['write'], ['lol', 'lol']);
        $socket->expects($this->at(0))
            ->method('write')
            ->with("lol", "<172>: ");
        $socket->expects($this->at(1))
            ->method('write')
            ->with("hej", "<172>: ");

        $handler->setSocket($socket);

        $handler->handle($this->getRecordWithMessage("hej\nlol"));
    }

    protected function getRecordWithMessage($msg)
    {
        return ['message' => $msg, 'level' => \Monolog\Logger::WARNING, 'context' => null, 'extra' => [], 'channel' => 'lol'];
    }
}
