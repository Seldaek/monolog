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
use Monolog\Level;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Robert Kaufmann III <rok3@rok3.me>
 */
class LogEntriesHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    private LogEntriesHandler&MockObject $handler;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->res);
    }

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'Critical write test'));

        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] test.CRITICAL: Critical write test/', $content);
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

        $this->assertMatchesRegularExpression('/(testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] .* \[\] \[\]\n){3}/', $content);
    }

    private function createHandler()
    {
        $useSSL = extension_loaded('openssl');
        $args = ['testToken', $useSSL, Level::Debug, true];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder('Monolog\Handler\LogEntriesHandler')
            ->setConstructorArgs($args)
            ->onlyMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->will($this->returnValue($this->res));
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->will($this->returnValue(true));
        $this->handler->expects($this->any())
            ->method('closeSocket')
            ->will($this->returnValue(true));
    }
}
