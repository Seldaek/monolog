<?php

namespace Monolog\Handler;

use Monolog\Formatter\ChromePHPFormatter;
use Monolog\Logger;

class SyslogUdpHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \UnexpectedValueException
     */
    public function testWeValidateFacilities()
    {
        new SyslogUdpHandler("ip", null, "invalidFacility");
    }

    /**
     *
     */
    public function testWeSplitIntoLines()
    {
        $handler = new SyslogUdpHandler("127.0.0.1", 514, "authpriv");
        $handler->setFormatter(new ChromePHPFormatter());

        $socket = $this->getMock('\Monolog\Handler\SyslogUdp\UdpSocket', array('write'), array('lol', 'lol'));
        $socket->expects($this->at(0))
            ->method('write')
            ->with("lol", "<" . (LOG_AUTHPRIV + LOG_WARNING) . ">: ");
        $socket->expects($this->at(1))
            ->method('write')
            ->with("hej", "<" . (LOG_AUTHPRIV + LOG_WARNING) . ">: ");

        $handler->setSocket($socket);

        $handler->handle($this->getRecordWithMessage("hej\nlol"));
    }

    /**
     * @param string $msg
     *
     * @return array
     */
    protected function getRecordWithMessage($msg)
    {
        return array('message' => $msg, 'level' => Logger::WARNING, 'context' => null, 'extra' => array(),
                     'channel' => 'lol');
    }
}
