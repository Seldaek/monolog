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

use Monolog\TestCase;
use Monolog\Logger;
use Monolog\Handler\SocketHandler\Exception\ConnectionException;
use Monolog\Handler\SocketHandler\Exception\WriteToSocketException;

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
     * @expectedException Monolog\Handler\SocketHandler\Exception\ConnectionException
     */
    public function testInvalidHostname() {
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
        $this->handler->setConnectionTimeout(10);
        $this->assertEquals(10, $this->handler->getConnectionTimeout());
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
        $this->handler->setTimeout(10);
        $this->assertEquals(10, $this->handler->getTimeout());
    }
    
    public function testSetConnectionString()
    {
        $this->createHandler('tcp://localhost:9090');
        $this->assertEquals('tcp://localhost:9090', $this->handler->getConnectionString());
    }
    
    public function testConnectionRefuesed()
    {
        try {
            $this->createHandler('127.0.0.1:7894');
            $string = 'Hello world';
            $this->writeRecord($string);
            $this->fail("Shoul not connect - are you running a server on 127.0.0.1:7894 ?");
        } catch (\Monolog\Handler\SocketHandler\Exception\ConnectionException $e) {
        }
    }
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\ConnectionException
     */
    public function testConnectionTimeoutWithMock()
    {
        $this->setMockHandler(array('createSocketResource'));
        $this->handler->expects($this->once())
                      ->method('createSocketResource')
                      ->will($this->throwException(new ConnectionException()));
        $this->writeRecord('Hello world');
    }
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\WriteToSocketException
     */
    public function testWriteFailsOnIfFwriteReturnsFalse()
    {
        $this->setMockHandler(array('fwrite'));
        
        $map = array(
            array('Hello world', 6),
            array('world', false),
        );
        
        $this->handler->expects($this->exactly(2))
                      ->method('fwrite')
                      ->will($this->returnValueMap($map));
        
        $this->injectMemoryResource();
        $this->writeRecord('Hello world');
    }
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\WriteToSocketException
     */
    public function testWriteFailsIfStreamTimesOut()
    {
        $this->setMockHandler(array('fwrite', 'stream_get_meta_data'));
        
        $map = array(
            array('Hello world', 6),
            array('world', 5),
        );
        
        $this->handler->expects($this->exactly(1))
                      ->method('fwrite')
                      ->will($this->returnValueMap($map));
        $this->handler->expects($this->exactly(1))
                      ->method('stream_get_meta_data')
                      ->will($this->returnValue(array('timed_out' => true)));
        
        
        $this->injectMemoryResource();
        $this->writeRecord('Hello world');
    }
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\WriteToSocketException
     */
    public function testWriteFailsOnIncompleteWrite()
    {
        $this->setMockHandler(array('fwrite', 'isConnected'));
        
        $map = array(
            array('Hello world', 6),
            array('world', 5),
        );
        
        $this->handler->expects($this->exactly(1))
                      ->method('fwrite')
                      ->will($this->returnValueMap($map));
        $this->handler->expects($this->at(0))
                      ->method('isConnected')
                      ->will($this->returnValue(true));
        $this->handler->expects($this->at(1))
                      ->method('isConnected')
                      ->will($this->returnValue(true));
        $this->handler->expects($this->at(2))
                      ->method('isConnected')
                      ->will($this->returnValue(false));
        
        $this->injectMemoryResource();
        $this->writeRecord('Hello world');
    }
    
    public function testWriteWithMemoryFile()
    {
        $this->createHandler('localhost:54321');
        $this->injectMemoryResource();
        $this->writeRecord('test1');
        $this->writeRecord('test2');
        $this->writeRecord('test3');
        fseek($this->res, 0);
        $this->assertEquals('test1test2test3', fread($this->res, 1024));
    }
    
    public function testWriteWithMock()
    {
        $this->setMockHandler(array('fwrite'));
        
        $map = array(
            array('Hello world', 6),
            array('world', 5),
        );
        
        $this->handler->expects($this->exactly(2))
                      ->method('fwrite')
                      ->will($this->returnValueMap($map));
        
        $this->injectMemoryResource();
        $this->writeRecord('Hello world');
    }
    
    public function testClose()
    {
        $this->createHandler('localhost:54321');
        $this->injectMemoryResource();
        $this->writeRecord('Hello world');
        $this->assertTrue(is_resource($this->res));
        $this->handler->close();
        $this->assertFalse(is_resource($this->res));
    }
    
    public function testCloseDoesNotClosePersistentSocket()
    {
        $this->createHandler('localhost:54321');
        $this->handler->setPersistent(true);
        $this->injectMemoryResource();
        $this->writeRecord('Hello world');
        $this->assertTrue(is_resource($this->res));
        $this->handler->close();
        $this->assertTrue(is_resource($this->res));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInjectBadResourceThrowsException()
    {
        $this->createHandler('');
        $this->handler->setResource('');
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
    
    private function injectMemoryResource()
    {
        $this->res = fopen('php://memory', 'a');
        $this->handler->setResource($this->res);
    }
    
    private function setMockHandler(array $methods)
    {
        $this->handler = $this->getMock(
                '\Monolog\Handler\SocketHandler',
                $methods,
                array('localhost:1234')
        );
        $this->handler->setFormatter($this->getIdentityFormatter());
    }
}
