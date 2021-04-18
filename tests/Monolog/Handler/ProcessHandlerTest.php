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

class ProcessHandlerTest extends TestCase
{
    /**
     * Dummy command to be used by tests that should not fail due to the command.
     *
     * @var string
     */
    const DUMMY_COMMAND = 'echo';

    /**
     * @covers Monolog\Handler\ProcessHandler::__construct
     * @covers Monolog\Handler\ProcessHandler::guardAgainstInvalidCommand
     * @covers Monolog\Handler\ProcessHandler::guardAgainstInvalidCwd
     * @covers Monolog\Handler\ProcessHandler::write
     * @covers Monolog\Handler\ProcessHandler::ensureProcessIsStarted
     * @covers Monolog\Handler\ProcessHandler::startProcess
     * @covers Monolog\Handler\ProcessHandler::handleStartupErrors
     */
    public function testWriteOpensProcessAndWritesToStdInOfProcess()
    {
        $fixtures = [
            'chuck norris',
            'foobar1337',
        ];

        $mockBuilder = $this->getMockBuilder('Monolog\Handler\ProcessHandler');
        $mockBuilder->onlyMethods(['writeProcessInput']);
        // using echo as command, as it is most probably available
        $mockBuilder->setConstructorArgs([self::DUMMY_COMMAND]);

        $handler = $mockBuilder->getMock();

        $handler->expects($this->exactly(2))
            ->method('writeProcessInput')
            ->withConsecutive([$this->stringContains($fixtures[0])], [$this->stringContains($fixtures[1])]);

        /** @var ProcessHandler $handler */
        $handler->handle($this->getRecord(Logger::WARNING, $fixtures[0]));
        $handler->handle($this->getRecord(Logger::ERROR, $fixtures[1]));
    }

    /**
     * Data provider for invalid commands.
     *
     * @return array
     */
    public function invalidCommandProvider()
    {
        return [
            [1337, 'TypeError'],
            ['', 'InvalidArgumentException'],
            [null, 'TypeError'],
            [fopen('php://input', 'r'), 'TypeError'],
        ];
    }

    /**
     * @dataProvider invalidCommandProvider
     * @param mixed $invalidCommand
     * @covers Monolog\Handler\ProcessHandler::guardAgainstInvalidCommand
     */
    public function testConstructWithInvalidCommandThrowsInvalidArgumentException($invalidCommand, $expectedExcep)
    {
        $this->expectException($expectedExcep);
        new ProcessHandler($invalidCommand, Logger::DEBUG);
    }

    /**
     * Data provider for invalid CWDs.
     *
     * @return array
     */
    public function invalidCwdProvider()
    {
        return [
            [1337, 'TypeError'],
            ['', 'InvalidArgumentException'],
            [fopen('php://input', 'r'), 'TypeError'],
        ];
    }

    /**
     * @dataProvider invalidCwdProvider
     * @param mixed $invalidCwd
     * @covers Monolog\Handler\ProcessHandler::guardAgainstInvalidCwd
     */
    public function testConstructWithInvalidCwdThrowsInvalidArgumentException($invalidCwd, $expectedExcep)
    {
        $this->expectException($expectedExcep);
        new ProcessHandler(self::DUMMY_COMMAND, Logger::DEBUG, true, $invalidCwd);
    }

    /**
     * @covers Monolog\Handler\ProcessHandler::__construct
     * @covers Monolog\Handler\ProcessHandler::guardAgainstInvalidCwd
     */
    public function testConstructWithValidCwdWorks()
    {
        $handler = new ProcessHandler(self::DUMMY_COMMAND, Logger::DEBUG, true, sys_get_temp_dir());
        $this->assertInstanceOf(
            'Monolog\Handler\ProcessHandler',
            $handler,
            'Constructed handler is not a ProcessHandler.'
        );
    }

    /**
     * @covers Monolog\Handler\ProcessHandler::handleStartupErrors
     */
    public function testStartupWithFailingToSelectErrorStreamThrowsUnexpectedValueException()
    {
        $mockBuilder = $this->getMockBuilder('Monolog\Handler\ProcessHandler');
        $mockBuilder->onlyMethods(['selectErrorStream']);
        $mockBuilder->setConstructorArgs([self::DUMMY_COMMAND]);

        $handler = $mockBuilder->getMock();

        $handler->expects($this->once())
            ->method('selectErrorStream')
            ->will($this->returnValue(false));

        $this->expectException(\UnexpectedValueException::class);
        /** @var ProcessHandler $handler */
        $handler->handle($this->getRecord(Logger::WARNING, 'stream failing, whoops'));
    }

    /**
     * @covers Monolog\Handler\ProcessHandler::handleStartupErrors
     * @covers Monolog\Handler\ProcessHandler::selectErrorStream
     */
    public function testStartupWithErrorsThrowsUnexpectedValueException()
    {
        $handler = new ProcessHandler('>&2 echo "some fake error message"');

        $this->expectException(\UnexpectedValueException::class);

        $handler->handle($this->getRecord(Logger::WARNING, 'some warning in the house'));
    }

    /**
     * @covers Monolog\Handler\ProcessHandler::write
     */
    public function testWritingWithErrorsOnStdOutOfProcessThrowsInvalidArgumentException()
    {
        $mockBuilder = $this->getMockBuilder('Monolog\Handler\ProcessHandler');
        $mockBuilder->onlyMethods(['readProcessErrors']);
        // using echo as command, as it is most probably available
        $mockBuilder->setConstructorArgs([self::DUMMY_COMMAND]);

        $handler = $mockBuilder->getMock();

        $handler->expects($this->exactly(2))
            ->method('readProcessErrors')
            ->willReturnOnConsecutiveCalls('', $this->returnValue('some fake error message here'));

        $this->expectException(\UnexpectedValueException::class);
        /** @var ProcessHandler $handler */
        $handler->handle($this->getRecord(Logger::WARNING, 'some test stuff'));
    }

    /**
     * @covers Monolog\Handler\ProcessHandler::close
     */
    public function testCloseClosesProcess()
    {
        $class = new \ReflectionClass('Monolog\Handler\ProcessHandler');
        $property = $class->getProperty('process');
        $property->setAccessible(true);

        $handler = new ProcessHandler(self::DUMMY_COMMAND);
        $handler->handle($this->getRecord(Logger::WARNING, '21 is only the half truth'));

        $process = $property->getValue($handler);
        $this->assertTrue(is_resource($process), 'Process is not running although it should.');

        $handler->close();

        $process = $property->getValue($handler);
        $this->assertFalse(is_resource($process), 'Process is still running although it should not.');
    }
}
