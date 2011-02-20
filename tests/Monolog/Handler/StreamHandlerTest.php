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

class StreamHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testWrite()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new StreamHandler($handle);
        $handler->write(array('message' => 'test'));
        $handler->write(array('message' => 'test2'));
        $handler->write(array('message' => 'test3'));
        fseek($handle, 0);
        $this->assertEquals('testtest2test3', fread($handle, 100));
    }

    public function testClose()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new StreamHandler($handle);
        $this->assertTrue(is_resource($handle));
        $handler->close();
        $this->assertFalse(is_resource($handle));
    }

    public function testWriteCreatesTheStreamResource()
    {
        $handler = new StreamHandler('php://memory');
        $handler->write(array('message' => 'test'));
    }

    /**
     * @expectedException LogicException
     */
    public function testWriteMissingResource()
    {
        $handler = new StreamHandler(null);
        $handler->write(array('message' => 'test'));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testWriteInvalidResource()
    {
        $handler = new StreamHandler('bogus://url');
        @$handler->write(array('message' => 'test'));
    }
}
