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

use Monolog\Handler\StreamHandler;
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
        $this->assertStringContainsString('testfoo', stream_get_contents($stream));
        $serialized = serialize($handler);
        $this->assertFalse(is_resource($stream));

        $handler = unserialize($serialized);
        $handler->handle($this->getRecord(Logger::WARNING, 'testbar'));
        $stream = $handler->getStream();

        $this->assertTrue(is_resource($stream));
        fseek($stream, 0);
        $contents = stream_get_contents($stream);
        $this->assertStringNotContainsString('testfoo', $contents);
        $this->assertStringContainsString('testbar', $contents);
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
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteMissingResource()
    {
        $this->expectException(\LogicException::class);

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
     * @covers Monolog\Handler\StreamHandler::__construct
     */
    public function testWriteInvalidArgument($invalidArgument)
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = new StreamHandler($invalidArgument);
    }

    /**
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteInvalidResource()
    {
        $this->expectException(\UnexpectedValueException::class);
        $php7xMessage = <<<STRING
The stream or file "bogus://url" could not be opened in append mode: failed to open stream: No such file or directory
The exception occurred while attempting to log: test
Context: {"foo":"bar"}
Extra: [1,2,3]
STRING;

        $php8xMessage = <<<STRING
The stream or file "bogus://url" could not be opened in append mode: Failed to open stream: No such file or directory
The exception occurred while attempting to log: test
Context: {"foo":"bar"}
Extra: [1,2,3]
STRING;

        $phpVersionString = phpversion();
        $phpVersionComponents = explode('.', $phpVersionString);
        $majorVersion = (int) $phpVersionComponents[0];

        $this->expectExceptionMessage(($majorVersion >= 8) ? $php8xMessage : $php7xMessage);

        $handler = new StreamHandler('bogus://url');
        $record = $this->getRecord();
        $record['context'] = ['foo' => 'bar'];
        $record['extra'] = [1, 2, 3];
        $handler->handle($record);
    }

    /**
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     */
    public function testWriteNonExistingResource()
    {
        $this->expectException(\UnexpectedValueException::class);

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
     * @covers Monolog\Handler\StreamHandler::__construct
     * @covers Monolog\Handler\StreamHandler::write
     * @dataProvider provideNonExistingAndNotCreatablePath
     */
    public function testWriteNonExistingAndNotCreatablePath($nonExistingAndNotCreatablePath)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Permissions checks can not run on windows');
        }

        $handler = null;

        try {
            $handler = new StreamHandler($nonExistingAndNotCreatablePath);
        } catch (\Exception $fail) {
            $this->fail(
                'A non-existing and not creatable path should throw an Exception earliest on first write.
                 Not during instantiation.'
            );
        }

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('There is no existing directory at');

        $handler->handle($this->getRecord());
    }

    public function provideNonExistingAndNotCreatablePath()
    {
        return [
            '/foo/bar/…' => [
                '/foo/bar/'.rand(0, 10000).DIRECTORY_SEPARATOR.rand(0, 10000),
            ],
            'file:///foo/bar/…' => [
                'file:///foo/bar/'.rand(0, 10000).DIRECTORY_SEPARATOR.rand(0, 10000),
            ],
        ];
    }

    public function provideMemoryValues()
    {
        return [
            ['1M', (int) (1024*1024/10)],
            ['10M', (int) (1024*1024)],
            ['1024M', (int) (1024*1024*1024/10)],
            ['1G', (int) (1024*1024*1024/10)],
            ['2000M', (int) (2000*1024*1024/10)],
            ['2050M', (int) (2050*1024*1024/10)],
            ['2048M', (int) (2048*1024*1024/10)],
            ['3G', (int) (3*1024*1024*1024/10)],
            ['2560M', (int) (2560*1024*1024/10)],
        ];
    }

    /**
     * @dataProvider provideMemoryValues
     * @return void
     */
    public function testPreventOOMError($phpMemory, $expectedChunkSize)
    {
        $previousValue = ini_set('memory_limit', $phpMemory);

        if ($previousValue === false) {
            $this->markTestSkipped('We could not set a memory limit that would trigger the error.');
        }

        try {
            $stream = tmpfile();

            if ($stream === false) {
                $this->markTestSkipped('We could not create a temp file to be use as a stream.');
            }

            $handler = new StreamHandler($stream);
            stream_get_contents($stream, 1024);

            $this->assertEquals($expectedChunkSize, $handler->getStreamChunkSize());
        } finally {
            ini_set('memory_limit', $previousValue);
        }
    }

    /**
     * @return void
     */
    public function testSimpleOOMPrevention()
    {
        $previousValue = ini_set('memory_limit', '2048M');

        if ($previousValue === false) {
            $this->markTestSkipped('We could not set a memory limit that would trigger the error.');
        }

        try {
            $stream = tmpfile();
            new StreamHandler($stream);
            stream_get_contents($stream);
            $this->assertTrue(true);
        } finally {
            ini_set('memory_limit', $previousValue);
        }
    }
}
