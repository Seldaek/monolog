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

use Monolog\Level;
use Monolog\Test\TestCase;

/**
 * @requires extension sockets
 */
class SyslogUdpHandlerTest extends TestCase
{
    public function testWeValidateFacilities()
    {
        $this->expectException(\UnexpectedValueException::class);

        $handler = new SyslogUdpHandler("ip", 514, "invalidFacility");
    }

    public function testWeSplitIntoLines()
    {
        $pid = getmypid();
        $host = gethostname();

        $handler = new \Monolog\Handler\SyslogUdpHandler("127.0.0.1", 514, "authpriv");
        $handler->setFormatter(new \Monolog\Formatter\ChromePHPFormatter());

        $time = '2014-01-07T12:34:56+00:00';
        $socket = $this->getMockBuilder('Monolog\Handler\SyslogUdp\UdpSocket')
            ->onlyMethods(['write'])
            ->setConstructorArgs(['lol'])
            ->getMock();
        $socket->expects($this->atLeast(2))
            ->method('write')
            ->withConsecutive(
                [$this->equalTo("lol"), $this->equalTo("<".(LOG_AUTHPRIV + LOG_WARNING).">1 $time $host php $pid - - ")],
                [$this->equalTo("hej"), $this->equalTo("<".(LOG_AUTHPRIV + LOG_WARNING).">1 $time $host php $pid - - ")],
            );

        $handler->setSocket($socket);

        $handler->handle($this->getRecordWithMessage("hej\nlol"));
    }

    public function testSplitWorksOnEmptyMsg()
    {
        $handler = new SyslogUdpHandler("127.0.0.1", 514, "authpriv");
        $handler->setFormatter($this->getIdentityFormatter());

        $socket = $this->getMockBuilder('Monolog\Handler\SyslogUdp\UdpSocket')
            ->onlyMethods(['write'])
            ->setConstructorArgs(['lol'])
            ->getMock();
        $socket->expects($this->never())
            ->method('write');

        $handler->setSocket($socket);

        $handler->handle($this->getRecordWithMessage(''));
    }

    public function testRfc()
    {
        $time = 'Jan 07 12:34:56';
        $pid = getmypid();
        $host = gethostname();

        $handler = $this->getMockBuilder('\Monolog\Handler\SyslogUdpHandler')
            ->setConstructorArgs(["127.0.0.1", 514, "authpriv", 'debug', true, "php", \Monolog\Handler\SyslogUdpHandler::RFC3164])
            ->onlyMethods([])
            ->getMock();

        $handler->setFormatter(new \Monolog\Formatter\ChromePHPFormatter());

        $socket = $this->getMockBuilder('\Monolog\Handler\SyslogUdp\UdpSocket')
            ->setConstructorArgs(['lol', 999])
            ->onlyMethods(['write'])
            ->getMock();
        $socket->expects($this->atLeast(2))
            ->method('write')
            ->withConsecutive(
                [$this->equalTo("lol"), $this->equalTo("<".(LOG_AUTHPRIV + LOG_WARNING).">$time $host php[$pid]: ")],
                [$this->equalTo("hej"), $this->equalTo("<".(LOG_AUTHPRIV + LOG_WARNING).">$time $host php[$pid]: ")],
            );

        $handler->setSocket($socket);

        $handler->handle($this->getRecordWithMessage("hej\nlol"));
    }

    protected function getRecordWithMessage($msg)
    {
        return $this->getRecord(message: $msg, level: Level::Warning, channel: 'lol', datetime: new \DateTimeImmutable('2014-01-07 12:34:56'));
    }
}
