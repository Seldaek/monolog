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
    public function testCloseKeepsExternalHandlersOpen()
    {
        $handle = fopen('php://memory', 'a+');
        $handler = new StreamHandler($handle);
        $this->assertTrue(is_resource($handle));
        $handler->close();
        $this->assertTrue(is_resource($handle));
    }

    /**
     * @covers Monolog\Handler\StreamHandler::close
     */
    public function testClose()
    {
        $handler = new StreamHandler('php://memory');
        $handler->handle($this->getRecord(Logger::WARNING, 'test'));
        $stream = $handler->getStream();

        $this->assertTrue(is_resource($stream));
        $handler->close();
        $this->assertFalse(is_resource($stream));
    }

    /**
     * @covers Monolog\Handler\StreamHandler::close
     * @covers Monolog\Handler\Handler::__sleep
     */
    public function testSerialization()
    {
        $handler = new StreamHandler('php://memory');
        $handler->handle($this->getRecord(Logger::WARNING, 'testfoo'));
        $stream = $handler->getStream();

        $this->assertTrue(is_resource($stream));
        fseek($stream, 0);
        $this->assertContains('testfoo', stream_get_contents($stream));
        $serialized = serialize($handler);
        $this->assertFalse(is_resource($stream));

        $handler = unserialize($serialized);
        $handler->handle($this->getRecord(Logger::WARNING, 'testbar'));
        $stream = $handler->getStream();

        $this->assertTrue(is_resource($stream));
        fseek($stream, 0);
        $contents = stream_get_contents($stream);
        $this->assertNotContains('testfoo', $contents);
        $this->assertContains('testbar', $contents);
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
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteLocking()
    {
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monolog_locked_log';
        $handler = new StreamHandler($temp, Logger::DEBUG, true, null, true);
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

    public function invalidArgumentProvider()
    {
        return [
            [1],
            [[]],
            [['bogus://url']],
        ];
    }

    /**
     * @dataProvider invalidArgumentProvider
     * @expectedException InvalidArgumentException
     * @covers Monolog\Handler\StreamHandler::__construct
     */
    public function testWriteInvalidArgument($invalidArgument)
    {
        $handler = new StreamHandler($invalidArgument);
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
        $handler = new StreamHandler('ftp://foo/bar/baz/'.rand(0, 10000));
        $handler->handle($this->getRecord());
    }

    /**
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteNonExistingPath()
    {
        $handler = new StreamHandler(sys_get_temp_dir().'/bar/'.rand(0, 10000).DIRECTORY_SEPARATOR.rand(0, 10000));
        $handler->handle($this->getRecord());
    }

    /**
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteNonExistingFileResource()
    {
        $handler = new StreamHandler('file://'.sys_get_temp_dir().'/bar/'.rand(0, 10000).DIRECTORY_SEPARATOR.rand(0, 10000));
        $handler->handle($this->getRecord());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /There is no existing directory at/
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteNonExistingAndNotCreatablePath()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Permissions checks can not run on windows');
        }
        $handler = new StreamHandler('/foo/bar/'.rand(0, 10000).DIRECTORY_SEPARATOR.rand(0, 10000));
        $handler->handle($this->getRecord());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /There is no existing directory at/
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteNonExistingAndNotCreatableFileResource()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Permissions checks can not run on windows');
        }
        $handler = new StreamHandler('file:///foo/bar/'.rand(0, 10000).DIRECTORY_SEPARATOR.rand(0, 10000));
        $handler->handle($this->getRecord());
    }
}
