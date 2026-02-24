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

/**
 * @coversDefaultClass \Monolog\Handler\WebhookHandler
 */
class WebhookHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * Default URLs to use in tests
     */
    const URL_HTTP = 'http://example.com/webhook';
    const URL_HTTP_NO_PATH = 'http://sub.example.com';
    const URL_HTTPS = 'https://example.com/webhook';
    const URL_HTTPS_NO_PATH = 'https://sub.example.com';
    const URL_INVALID = '.ht!tp:/\invalid-url';

    /**
     * @covers ::__construct
     */
    public function testConstructorSetsExpectedDefaults()
    {
        [$handler, $output] = $this->createHandler(self::URL_HTTP);

        $level = $handler->getLevel();
        $bubble = $handler->getBubble();
        $persistent = $handler->isPersistent();
        $timeout = $handler->getTimeout();
        $writingTimeout = $handler->getWritingTimeout();

        fclose($output);

        $this->assertEquals(Level::Debug, $level);
        $this->assertEquals(true, $bubble);
        $this->assertEquals(false, $persistent);
        $this->assertEquals(0.0, $timeout);
        $this->assertEquals(10.0, $writingTimeout);
    }

    /**
     * @covers ::__construct
     */
    public function testThrowsUrlInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided URL is not valid.');

        $this->createHandler(self::URL_INVALID);
    }

    /**
     * @covers ::__construct
     */
    public function testThrowsOpensslMissingExtensionException()
    {
        if (\extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl to be missing to run');
        }
        $this->expectException(MissingExtensionException::class);
        $this->expectExceptionMessage('The OpenSSL PHP extension is required when using an HTTPS webhook URL.');

        $this->createHandler(self::URL_HTTPS);
    }

    /**
     * @covers ::__construct
     */
    public function testHttpConnectionString()
    {
        [$httpHandler, $httpOutput] = $this->createHandler(self::URL_HTTP);
        [$httpNoPathHandler, $httpNoPathOutput] = $this->createHandler(self::URL_HTTP_NO_PATH);

        $httpConnectionString = $httpHandler->getConnectionString();
        $httpNoPathConnectionString = $httpNoPathHandler->getConnectionString();

        fclose($httpOutput);
        fclose($httpNoPathOutput);

        $this->assertEquals('tcp://example.com:80', $httpConnectionString);
        $this->assertEquals('tcp://sub.example.com:80', $httpNoPathConnectionString);
    }

    /**
     * @covers ::__construct
     */
    public function testHttpsConnectionString()
    {
        if (!\extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl to run');
        }

        [$httpsHandler, $httpsOutput] = $this->createHandler(self::URL_HTTPS);
        [$httpsNoPathHandler, $httpsNoPathOutput] = $this->createHandler(self::URL_HTTPS_NO_PATH);

        $httpsConnectionString = $httpsHandler->getConnectionString();
        $httpsNoPathConnectionString = $httpsNoPathHandler->getConnectionString();

        fclose($httpsOutput);
        fclose($httpsNoPathOutput);

        $this->assertEquals('ssl://example.com:443', $httpsConnectionString);
        $this->assertEquals('ssl://sub.example.com:443', $httpsNoPathConnectionString);
    }

    /**
     * @covers ::write
     */
    public function testWriteOutput()
    {
        [$handler, $output] = $this->createHandler(self::URL_HTTP);

        $record = $this->getRecord(Level::Error, 'test message');
        $handler->handle($record);

        rewind($output);
        $written = stream_get_contents($output);
        fclose($output);

        $this->assertMatchesRegularExpression('/POST \/webhook HTTP\/1.1\\r\\nHost: example\.com\\r\\nContent-Type: application\/json\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n{"message":"test message","context":\[\],"level":400,"level_name":"ERROR","channel":"test","datetime":".*","extra":\[\]}/', $written);
    }

    /**
     * @covers ::write
     */
    public function testWriteOutputNoPath()
    {
        [$handler, $output] = $this->createHandler(self::URL_HTTP_NO_PATH);

        $record = $this->getRecord(Level::Error, 'test message');
        $handler->handle($record);

        rewind($output);
        $written = stream_get_contents($output);
        fclose($output);

        $this->assertMatchesRegularExpression('/POST \/ HTTP\/1.1\\r\\nHost: sub.example\.com\\r\\nContent-Type: application\/json\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n{"message":"test message","context":\[\],"level":400,"level_name":"ERROR","channel":"test","datetime":".*","extra":\[\]}/', $written);
    }

    private function createHandler($url)
    {
        $handler = $this->getMockBuilder('Monolog\Handler\WebhookHandler')
            ->setConstructorArgs([$url])
            ->onlyMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();
        
        $output = fopen('php://memory', 'a');

        $handler->expects($this->any())
            ->method('fsockopen')
            ->willReturn($output);
        $handler->expects($this->any())
            ->method('streamSetTimeout')
            ->willReturn(true);
        $handler->expects($this->any())
            ->method('closeSocket');

        return [$handler, $output];
    }
}
