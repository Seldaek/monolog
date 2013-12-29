<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\TestCase;

/**
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */
class SocketHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidHostname()
    {
        $handler = $this->createHandler('garbage://here');
        $this->writeRecord($handler, 'data');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadConnectionTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setConnectionTimeout(-1);
    }

    public function testSetConnectionTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setConnectionTimeout(10.1);
        self::assertEquals(10.1, $handler->getConnectionTimeout());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setTimeout(-1);
    }

    public function testSetTimeout()
    {
        $handler = $this->createHandler('localhost:1234');
        $handler->setTimeout(10.25);
        self::assertEquals(10.25, $handler->getTimeout());
    }

    public function testSetConnectionString()
    {
        $handler = $this->createHandler('tcp://localhost:9090');
        self::assertEquals('tcp://localhost:9090', $handler->getConnectionString());

        return $handler;
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testExceptionIsThrownOnFsockopenError()
    {
        $handler = $this->setMockHandler(array('fsockopen'));
        $handler->expects($this->once())
            ->method('fsockopen')
            ->will($this->returnValue(false));
        $this->writeRecord($handler, 'Hello world');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testExceptionIsThrownOnPfsockopenError()
    {
        $handler = $this->setMockHandler(array('pfsockopen'));
        $handler->expects($this->once())
            ->method('pfsockopen')
            ->will($this->returnValue(false));
        $handler->setPersistent(true);
        $this->writeRecord($handler, 'Hello world');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testExceptionIsThrownIfCannotSetTimeout()
    {
        $handler = $this->setMockHandler(array('streamSetTimeout'));
        $handler->expects($this->once())
            ->method('streamSetTimeout')
            ->will($this->returnValue(false));
        $this->writeRecord($handler, 'Hello world');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWriteFailsOnIfFwriteReturnsFalse()
    {
        $handler = $this->setMockHandler(array('fwrite'));

        $callback = function ($arg) {
            $map = array(
                'Hello world' => 6,
                'world'       => false,
            );

            return $map[$arg];
        };

        $handler->expects($this->exactly(2))
            ->method('fwrite')
            ->will($this->returnCallback($callback));

        $this->writeRecord($handler, 'Hello world');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWriteFailsIfStreamTimesOut()
    {
        $handler = $this->setMockHandler(array('fwrite', 'streamGetMetadata'));

        $callback = function ($arg) {
            $map = array(
                'Hello world' => 6,
                'world'       => 5,
            );

            return $map[$arg];
        };

        $handler->expects($this->exactly(1))
            ->method('fwrite')
            ->will($this->returnCallback($callback));
        $handler->expects($this->exactly(1))
            ->method('streamGetMetadata')
            ->will($this->returnValue(array('timed_out' => true)));

        $this->writeRecord($handler, 'Hello world');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWriteFailsOnIncompleteWrite()
    {
        $handler = $this->setMockHandler(array('fwrite', 'streamGetMetadata'));

        $res      = $this->res;
        $callback = function ($string) use ($res) {
            fclose($res);

            return strlen(substr($string, 0, 5));
        };

        $handler->expects($this->exactly(1))
            ->method('fwrite')
            ->will($this->returnCallback($callback));
        $handler->expects($this->exactly(1))
            ->method('streamGetMetadata')
            ->will($this->returnValue(array('timed_out' => false)));

        $this->writeRecord($handler, 'Hello world');
    }

    public function testWriteWithMemoryFile()
    {
        $handler = $this->setMockHandler();
        $this->writeRecord($handler, 'test1');
        $this->writeRecord($handler, 'test2');
        $this->writeRecord($handler, 'test3');
        fseek($this->res, 0);
        self::assertEquals('test1test2test3', fread($this->res, 1024));
    }

    public function testWriteWithMock()
    {
        $handler = $this->setMockHandler(array('fwrite'));

        $callback = function ($arg) {
            $map = array(
                'Hello world' => 6,
                'world'       => 5,
            );

            return $map[$arg];
        };

        $handler->expects($this->exactly(2))
            ->method('fwrite')
            ->will($this->returnCallback($callback));

        $this->writeRecord($handler, 'Hello world');
    }

    public function testClose()
    {
        $handler = $this->setMockHandler();
        $this->writeRecord($handler, 'Hello world');
        self::assertInternalType('resource', $this->res);
        $handler->close();
        self::assertFalse(is_resource($this->res), "Expected resource to be closed after closing handler");
    }

    public function testCloseDoesNotClosePersistentSocket()
    {
        $handler = $this->setMockHandler();
        $handler->setPersistent(true);
        $this->writeRecord($handler, 'Hello world');
        self::assertTrue(is_resource($this->res));
        $handler->close();
        self::assertTrue(is_resource($this->res));
    }

    /**
     * @param $connectionString
     *
     * @return SocketHandler
     */
    private function createHandler($connectionString)
    {
        $handler = new SocketHandler($connectionString);
        $handler->setFormatter($this->getIdentityFormatter());

        return $handler;
    }

    /**
     * @param SocketHandler $handler
     * @param string        $string
     */
    private function writeRecord(SocketHandler $handler, $string)
    {
        $handler->handle($this->getRecord(Logger::WARNING, $string));
    }

    /**
     * @param array $methods
     *
     * @return SocketHandler|\PHPUnit_Framework_MockObject_MockObject
     */
    private function setMockHandler(array $methods = array())
    {
        $this->res = fopen('php://memory', 'a');

        $defaultMethods = array('fsockopen', 'pfsockopen', 'streamSetTimeout');
        $newMethods     = array_diff($methods, $defaultMethods);

        $finalMethods = array_merge($defaultMethods, $newMethods);

        $handler = $this->getMock(
            '\Monolog\Handler\SocketHandler',
            $finalMethods,
            array('localhost:1234')
        );

        if (!in_array('fsockopen', $methods)) {
            $handler->expects($this->any())
                ->method('fsockopen')
                ->will($this->returnValue($this->res));
        }

        if (!in_array('pfsockopen', $methods)) {
            $handler->expects($this->any())
                ->method('pfsockopen')
                ->will($this->returnValue($this->res));
        }

        if (!in_array('streamSetTimeout', $methods)) {
            $handler->expects($this->any())
                ->method('streamSetTimeout')
                ->will($this->returnValue(true));
        }

        /** @var $handler \Monolog\Handler\SocketHandler */
        $handler->setFormatter($this->getIdentityFormatter());

        return $handler;
    }
}
