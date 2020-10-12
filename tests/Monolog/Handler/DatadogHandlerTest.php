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
 * @author Tristan Bessoussa <tristan.bessoussa@gmail.com>
 */
class DatadogHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * @var DatadogHandler
     */
    private $handler;

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'Critical write test'));

        fseek($this->resource, 0);
        $content = fread($this->resource, 1024);

        $this->assertRegexp('/^testToken \{"message":"Critical write test","context":\[\],"level":500,"channel":"test","datetime":"([^"]+)","extra":\[\],"logger.name":"monolog","host":"([^"]+)","ddsource":"php","status":"CRITICAL"\}\\n$/', $content);
    }

    public function testWriteBatchContent()
    {
        $records = [
            $this->getRecord(Logger::CRITICAL, 'Critical write test'),
            $this->getRecord(Logger::INFO, 'Info write test'),
            $this->getRecord(Logger::WARNING, 'Warning write test'),
        ];
        $this->createHandler();
        $this->handler->handleBatch($records);

        fseek($this->resource, 0);
        $content = fread($this->resource, 1024);

        $lines = explode("\n", $content);

        $this->assertRegexp('/^testToken \{"message":"Critical write test","context":\[\],"level":500,"channel":"test","datetime":"([^"]+)","extra":\[\],"logger.name":"monolog","host":"([^"]+)","ddsource":"php","status":"CRITICAL"\}$/', $lines[0]);
        $this->assertRegexp('/^testToken \{"message":"Info write test","context":\[\],"level":200,"channel":"test","datetime":"([^"]+)","extra":\[\],"logger.name":"monolog","host":"([^"]+)","ddsource":"php","status":"INFO"\}$/', $lines[1]);
        $this->assertRegexp('/^testToken \{"message":"Warning write test","context":\[\],"level":300,"channel":"test","datetime":"([^"]+)","extra":\[\],"logger.name":"monolog","host":"([^"]+)","ddsource":"php","status":"WARNING"\}$/', $lines[2]);
        $this->assertSame('', $lines[3]);
    }

    private function createHandler()
    {
        $useSSL = extension_loaded('openssl');
        $args = array('testToken', 'eu', $useSSL, Logger::DEBUG, true);
        $this->resource = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder(DatadogHandler::class)
            ->setMethods(array('fsockopen', 'streamSetTimeout', 'closeSocket'))
            ->setConstructorArgs($args)
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->will($this->returnValue($this->resource));
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->will($this->returnValue(true));
        $this->handler->expects($this->any())
            ->method('closeSocket')
            ->will($this->returnValue(true));
    }
}
