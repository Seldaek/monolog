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

use Monolog\Formatter\FlowdockFormatter;
use Monolog\Test\TestCase;
use Monolog\Level;

/**
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 * @see    https://www.hipchat.com/docs/api
 */
class FlowdockHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    private FlowdockHandler $handler;

    public function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl to run');
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->res);
    }

    public function testWriteHeader()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/POST \/v1\/messages\/team_inbox\/.* HTTP\/1.1\\r\\nHost: api.flowdock.com\\r\\nContent-Type: application\/json\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    /**
     * @depends testWriteHeader
     */
    public function testWriteContent($content)
    {
        $this->assertMatchesRegularExpression('/"source":"test_source"/', $content);
        $this->assertMatchesRegularExpression('/"from_address":"source@test\.com"/', $content);
    }

    private function createHandler($token = 'myToken')
    {
        $constructorArgs = [$token, Level::Debug];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder('Monolog\Handler\FlowdockHandler')
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->willReturn($this->res);
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->willReturn(true);
        $this->handler->expects($this->any())
            ->method('closeSocket');

        $this->handler->setFormatter(new FlowdockFormatter('test_source', 'source@test.com'));
    }
}
