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
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */
class SocketHandlerTest extends TestCase
{
    private SocketHandler&MockObject $handler;

    /**
     * @var resource
     */
    private $res;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->res);
    }

    public function testInvalidHostname()
    {
        $this->expectException(\UnexpectedValueException::class);

        $handler = $this->createHandler('garbage://here');
        $handler->handle($this->getRecord(Level::Warning, 'data'));
    }

    public function testBadConnectionTimeout()
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = $this->createHandler('localhost:1234');
        $handler->setConnectionTimeout(-1);
    }

    public function testSetConnectionTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setConnectionTimeout(10.1);
        $this->assertEquals(10.1, $handler->getConnectionTimeout());
    }

    public function testBadTimeout()
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = $this->createHandler('localhost:1234');
        $handler->setTimeout(-1);
    }

    public function testSetTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setTimeout(10.25);
        $this->assertEquals(10.25, $handler->getTimeout());
    }

    public function testSetWritingTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setWritingTimeout(10.25);
        $this->assertEquals(10.25, $handler->getWritingTimeout());
    }

    public function testSetChunkSize()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setChunkSize(1025);
        $this->assertEquals(1025, $handler->getChunkSize());
    }

    public function testSetConnectionString()
    {
        $handler = $this->createHandler('tcp://localhost:9090');
        $this->assertEquals('tcp://localhost:9090', $handler->getConnectionString());
    }

    public function testExceptionIsThrownOnFsockopenError()
    {
        $this->setMockHandler(['fsockopen']);
        $this->handler->expects($this->once())
            ->method('fsockopen')
            ->will($this->returnValue(false));

        $this->expectException(\UnexpectedValueException::class);

        $this->writeRecord('Hello world');
    }

    public function testExceptionIsThrownOnPfsockopenError()
    {
        $this->setMockHandler(['pfsockopen']);
        $this->handler->expects($this->once())
            ->method('pfsockopen')
            ->will($this->returnValue(false));

        $this->handler->setPersistent(true);

        $this->expectException(\UnexpectedValueException::class);

        $this->writeRecord('Hello world');
    }

    public function testExceptionIsThrownIfCannotSetTimeout()
    {
        $this->setMockHandler(['streamSetTimeout']);
        $this->handler->expects($this->once())
            ->method('streamSetTimeout')
            ->will($this->returnValue(false));

        $this->expectException(\UnexpectedValueException::class);

        $this->writeRecord('Hello world');
    }

    public function testExceptionIsThrownIfCannotSetChunkSize()
    {
        $this->setMockHandler(['streamSetChunkSize']);
        $this->handler->setChunkSize(8192);
        $this->handler->expects($this->once())
            ->method('streamSetChunkSize')
            ->will($this->returnValue(false));

        $this->expectException(\UnexpectedValueException::class);

        $this->writeRecord('Hello world');
    }

    public function testWriteFailsOnIfFwriteReturnsFalse()
    {
        $this->setMockHandler(['fwrite']);

        $callback = function ($arg) {
            $map = [
                'Hello world' => 6,
                'world' => false,
            ];

            return $map[$arg];
        };

        $this->handler->expects($this->exactly(2))
            ->method('fwrite')
            ->will($this->returnCallback($callback));

        $this->expectException(\RuntimeException::class);

        $this->writeRecord('Hello world');
    }

    public function testWriteFailsIfStreamTimesOut()
    {
        $this->setMockHandler(['fwrite', 'streamGetMetadata']);

        $callback = function ($arg) {
            $map = [
                'Hello world' => 6,
                'world' => 5,
            ];

            return $map[$arg];
        };

        $this->handler->expects($this->exactly(1))
            ->method('fwrite')
            ->will($this->returnCallback($callback));
        $this->handler->expects($this->exactly(1))
            ->method('streamGetMetadata')
            ->will($this->returnValue(['timed_out' => true]));

        $this->expectException(\RuntimeException::class);

        $this->writeRecord('Hello world');
    }

    public function testWriteFailsOnIncompleteWrite()
    {
        $this->setMockHandler(['fwrite', 'streamGetMetadata']);

        $res = $this->res;
        $callback = function ($string) use ($res) {
            fclose($res);

            return strlen('Hello');
        };

        $this->handler->expects($this->exactly(1))
            ->method('fwrite')
            ->will($this->returnCallback($callback));
        $this->handler->expects($this->exactly(1))
            ->method('streamGetMetadata')
            ->will($this->returnValue(['timed_out' => false]));

        $this->expectException(\RuntimeException::class);

        $this->writeRecord('Hello world');
    }

    public function testWriteWithMemoryFile()
    {
        $this->setMockHandler();
        $this->writeRecord('test1');
        $this->writeRecord('test2');
        $this->writeRecord('test3');
        fseek($this->res, 0);
        $this->assertEquals('test1test2test3', fread($this->res, 1024));
    }

    public function testWriteWithMock()
    {
        $this->setMockHandler(['fwrite']);

        $callback = function ($arg) {
            $map = [
                'Hello world' => 6,
                'world' => 5,
            ];

            return $map[$arg];
        };

        $this->handler->expects($this->exactly(2))
            ->method('fwrite')
            ->will($this->returnCallback($callback));

        $this->writeRecord('Hello world');
    }

    public function testClose()
    {
        $this->setMockHandler();
        $this->writeRecord('Hello world');
        $this->assertIsResource($this->res);
        $this->handler->close();
        $this->assertFalse(is_resource($this->res), "Expected resource to be closed after closing handler");
    }

    public function testCloseDoesNotClosePersistentSocket()
    {
        $this->setMockHandler();
        $this->handler->setPersistent(true);
        $this->writeRecord('Hello world');
        $this->assertTrue(is_resource($this->res));
        $this->handler->close();
        $this->assertTrue(is_resource($this->res));
    }

    public function testAvoidInfiniteLoopWhenNoDataIsWrittenForAWritingTimeoutSeconds()
    {
        $this->setMockHandler(['fwrite', 'streamGetMetadata']);

        $this->handler->expects($this->any())
            ->method('fwrite')
            ->will($this->returnValue(0));

        $this->handler->expects($this->any())
            ->method('streamGetMetadata')
            ->will($this->returnValue(['timed_out' => false]));

        $this->handler->setWritingTimeout(1);

        $this->expectException(\RuntimeException::class);

        $this->writeRecord('Hello world');
    }

    private function createHandler(string $connectionString): SocketHandler
    {
        $handler = new SocketHandler($connectionString);
        $handler->setFormatter($this->getIdentityFormatter());

        return $handler;
    }

    private function writeRecord($string)
    {
        $this->handler->handle($this->getRecord(Level::Warning, $string));
    }

    private function setMockHandler(array $methods = [])
    {
        $this->res = fopen('php://memory', 'a');

        $defaultMethods = ['fsockopen', 'pfsockopen', 'streamSetTimeout', 'streamSetChunkSize'];
        $newMethods = array_diff($methods, $defaultMethods);

        $finalMethods = array_merge($defaultMethods, $newMethods);

        $this->handler = $this->getMockBuilder('Monolog\Handler\SocketHandler')
            ->onlyMethods($finalMethods)
            ->setConstructorArgs(['localhost:1234'])
            ->getMock();

        if (!in_array('fsockopen', $methods)) {
            $this->handler->expects($this->any())
                ->method('fsockopen')
                ->will($this->returnValue($this->res));
        }

        if (!in_array('pfsockopen', $methods)) {
            $this->handler->expects($this->any())
                ->method('pfsockopen')
                ->will($this->returnValue($this->res));
        }

        if (!in_array('streamSetTimeout', $methods)) {
            $this->handler->expects($this->any())
                ->method('streamSetTimeout')
                ->will($this->returnValue(true));
        }

        if (!in_array('streamSetChunkSize', $methods)) {
            $this->handler->expects($this->any())
                ->method('streamSetChunkSize')
                ->will($this->returnValue(8192));
        }

        $this->handler->setFormatter($this->getIdentityFormatter());
    }
}
