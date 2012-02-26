<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Monolog\Handler\SocketHandler;

class SocketTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\ConnectionException
     */
    public function testInvalidHostname() {
        $socket = new Socket('garbage://here');
        $socket->write('data');
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadConnectionTimeout()
    {
        $socket = new Socket('localhost:1234');
        $socket->setConnectionTimeout(-1);
    }
    
    public function testSetConnectionTimeout()
    {
        $socket = new Socket('localhost:1234');
        $socket->setConnectionTimeout(10);
        $this->assertEquals(10, $socket->getConnectionTimeout());
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadTimeout()
    {
        $socket = new Socket('localhost:1234');
        $socket->setTimeout(-1);
    }
    
    public function testSetTimeout()
    {
        $socket = new Socket('localhost:1234');
        $socket->setTimeout(10);
        $this->assertEquals(10, $socket->getTimeout());
    }
    
    public function testSetConnectionString()
    {
        $socket = new Socket('tcp://localhost:9090');
        $this->assertEquals('tcp://localhost:9090', $socket->getConnectionString());
    }
    
    public function testConnectionRefuesed()
    {
        try {
            $socket = new Socket('127.0.0.1:7894');
            $socket->setTimeout(1);
            $string = 'Hello world';
            $socket->write($string);
            $this->fail("Shoul not connect - are you running a server on 127.0.0.1:7894 ?");
        } catch (\Monolog\Handler\SocketHandler\Exception\ConnectionException $e) {
        }
    }
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\ConnectionException
     */
    public function testConnectionTimeoutWithMock()
    {
        $socket = new MockSocket('localhost:54321');
        $socket->setConnectionTimeout(10);
        $socket->setFailConnectionTimeout(10);
        $socket->write('Hello world');
    }
    
    /**
     * @expectedException Monolog\Handler\SocketHandler\Exception\WriteToSocketException
     */
    public function testWriteTimeoutWithMock()
    {
        $socket = new MockSocket('localhost:54321');
        $socket->setTimeout(10);
        $socket->setFailTimeout(10);
        $socket->write('Hello world');
    }
    
    public function testWriteWithMock()
    {
        $socket = new MockSocket('localhost:54321');
        $socket->write('Hello world');
        $res = $socket->getResource();
        fseek($res, 0);
        $this->assertEquals('Hello world', fread($res, 1024));
    }
    
    public function testClose()
    {
        $resource = fopen('php://memory', 'a+');
        $socket = new MockSocket($resource);
        $this->assertTrue(is_resource($resource));
        $socket->close();
        $this->assertFalse(is_resource($resource));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInjectBadResourceThrowsException()
    {
        $socket = new Socket('');
        $socket->setResource('');
    }
}