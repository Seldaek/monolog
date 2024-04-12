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
 * @author Gabriel Machado <gabriel.ms1@hotmail.com>
 */
class InsightOpsHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $resource;

    private InsightOpsHandler&MockObject $handler;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->resource);
    }

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'Critical write test'));

        fseek($this->resource, 0);
        $content = fread($this->resource, 1024);

        $this->assertMatchesRegularExpression('/testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] test.CRITICAL: Critical write test/', $content);
    }

    public function testWriteBatchContent()
    {
        $this->createHandler();
        $this->handler->handleBatch($this->getMultipleRecords());

        fseek($this->resource, 0);
        $content = fread($this->resource, 1024);

        $this->assertMatchesRegularExpression('/(testToken \[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+00:00\] .* \[\] \[\]\n){3}/', $content);
    }

    private function createHandler()
    {
        $useSSL = extension_loaded('openssl');
        $args = ['testToken', 'us', $useSSL, Level::Debug, true];
        $this->resource = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder(InsightOpsHandler::class)
            ->onlyMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->setConstructorArgs($args)
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->willReturn($this->resource);
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->willReturn(true);
        $this->handler->expects($this->any())
            ->method('closeSocket');
    }
}
