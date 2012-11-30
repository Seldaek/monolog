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

class StreamHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWrite()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new StreamHandler($handle);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord(Logger::WARNING, 'test'));
        $handler->handle($this->getRecord(Logger::WARNING, 'test2'));
        $handler->handle($this->getRecord(Logger::WARNING, 'test3'));
        fseek($handle, 0);
        $this->assertEquals('testtest2test3', fread($handle, 100));
    }

    /**
     * @covers Monolog\Handler\StreamHandler::close
     */
    public function testClose()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new StreamHandler($handle);
        $this->assertTrue(is_resource($handle));
        $handler->close();
        $this->assertFalse(is_resource($handle));
    }

    /**
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteCreatesTheStreamResource()
    {
        $handler = new StreamHandler('php://memory');
        $handler->handle($this->getRecord());
    }

    /**
     * @expectedException LogicException
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteMissingResource()
    {
        $handler = new StreamHandler(null);
        $handler->handle($this->getRecord());
    }

    /**
     * @expectedException UnexpectedValueException
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteInvalidResource()
    {
        $handler = new StreamHandler('bogus://url');
        $handler->handle($this->getRecord());
    }

    /**
     * @expectedException UnexpectedValueException
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteNonExistingResource()
    {
        $handler = new StreamHandler('/foo/bar/baz/'.rand(0, 10000));
        $handler->handle($this->getRecord());
    }
}
