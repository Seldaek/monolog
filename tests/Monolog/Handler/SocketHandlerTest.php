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
use Monolog\Util\LocalSocket;

/**
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */
class SocketHandlerTest extends TestCase
{
    /**
     * @var Monolog\Handler\SocketHandler
     */
    private $handler;

    /**
     * @var resource
     */
    private $res;

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidHostname()
    {
        $this->createHandler('garbage://here');
        $this->writeRecord('data');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadConnectionTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setConnectionTimeout(-1);
    }

    public function testSetConnectionTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setConnectionTimeout(10.1);
        $this->assertEquals(10.1, $this->handler->getConnectionTimeout());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setTimeout(-1);
    }

    public function testSetTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setTimeout(10.25);
        $this->assertEquals(10.25, $this->handler->getTimeout());
    }

    public function testSetWritingTimeout()
    {
        $this->createHandler('localhost:1234');
        $this->handler->setWritingTimeout(10.25);
        $this->assertEquals(10.25, $this->handler->getWritingTimeout());
    }

    public function testSetConnectionString()
    {
        $this->createHandler('tcp://localhost:9090');
        $this->assertEquals('tcp://localhost:9090', $this->handler->getConnectionString());
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testExceptionIsThrownOnFsockopenError()
    {
        $this->createHandler('tcp://127.0.0.1:51985');

        $this->writeRecord('Hello world');
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testExceptionIsThrownOnPfsockopenError()
    {
        $this->createHandler('tcp://127.0.0.1:51985');
        $this->handler->setPersistent(true);

        $this->writeRecord('Hello world');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testWriteFailsOnIfFwriteReturnsFalse()
    {
        $this->initHandlerAndSocket();
        $this->writeRecord('Hello world');

        LocalSocket::shutdownSocket();

        $this->writeRecord('Hello world2');
    }

    public function testWriteRealSocket()
    {
        $this->initHandlerAndSocket();
        $this->writeRecord("foo bar baz content test1\n");
        $this->writeRecord("foo bar baz content test2\n");
        $this->writeRecord("foo bar baz content test3\n");

        $this->assertEquals("foo bar baz content test1\nfoo bar baz content test2\nfoo bar baz content test3\n", $this->socket->getOutput());
    }

    public function testClose()
    {
        $this->initHandlerAndSocket();
        $this->writeRecord('Hello world');

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'resource');
        $reflectionProperty->setAccessible(true);

        $this->assertInternalType('resource', $reflectionProperty->getValue($this->handler));
        $this->handler->close();
        $this->assertFalse(is_resource($reflectionProperty->getValue($this->handler)), "Expected resource to be closed after closing handler");
    }

    public function testCloseDoesNotClosePersistentSocket()
    {
        $this->initHandlerAndSocket();
        $this->handler->setPersistent(true);
        $this->writeRecord('Hello world');

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'resource');
        $reflectionProperty->setAccessible(true);

        $this->assertTrue(is_resource($reflectionProperty->getValue($this->handler)));
        $this->handler->close();
        $this->assertTrue(is_resource($reflectionProperty->getValue($this->handler)));
    }

    private function createHandler($connectionString)
    {
        $this->handler = new SocketHandler($connectionString);
        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    private function writeRecord($string)
    {
        $this->handler->handle($this->getRecord(Logger::WARNING, $string));
    }

    private function initHandlerAndSocket()
    {
        $this->socket = LocalSocket::initSocket();

        $this->handler = new SocketHandler('tcp://127.0.0.1:51984');
        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    public function tearDown()
    {
        unset($this->socket, $this->handler);
    }
}
