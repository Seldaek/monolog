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
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Julien Breux <julien.breux@gmail.com>
 */
class LogmaticHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @var resource
     */
    private $res;

    private LogmaticHandler&MockObject $handler;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->res);
        unset($this->handler);
    }

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'Critical write test'));

        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/testToken {"message":"Critical write test","context":{},"level":500,"level_name":"CRITICAL","channel":"test","datetime":"(.*)","extra":{},"hostname":"testHostname","appname":"testAppname","@marker":\["sourcecode","php"\]}/', $content);
    }

    public function testWriteBatchContent()
    {
        $records = [
            $this->getRecord(),
            $this->getRecord(),
            $this->getRecord(),
        ];
        $this->createHandler();
        $this->handler->handleBatch($records);

        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/testToken {"message":"test","context":{},"level":300,"level_name":"WARNING","channel":"test","datetime":"(.*)","extra":{},"hostname":"testHostname","appname":"testAppname","@marker":\["sourcecode","php"\]}/', $content);
    }

    private function createHandler()
    {
        $useSSL = \extension_loaded('openssl');
        $args = ['testToken', 'testHostname', 'testAppname', $useSSL, Level::Debug, true];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder('Monolog\Handler\LogmaticHandler')
            ->setConstructorArgs($args)
            ->onlyMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->willReturn($this->res);
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->willReturn(true);
        $this->handler->expects($this->any())
            ->method('closeSocket');
    }
}
