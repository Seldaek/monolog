<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use DateTimeZone;
use Monolog\Handler\TestHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\Test\LoggerInterfaceTest;

class PsrLogCompatTest extends TestCase
{
    private $handler;

    public function getLogger(): LoggerInterface
    {
        $logger = new Logger('foo');
        $logger->pushHandler($handler = new TestHandler);
        $logger->pushProcessor(new PsrLogMessageProcessor);
        $handler->setFormatter(new LineFormatter('%level_name% %message%'));

        $this->handler = $handler;

        return $logger;
    }

    public function getLogs(): array
    {
        $convert = function ($record) {
            $lower = function ($match) {
                return strtolower($match[0]);
            };

            return preg_replace_callback('{^[A-Z]+}', $lower, $record['formatted']);
        };

        return array_map($convert, $this->handler->getRecords());
    }

    public function testImplements()
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->getLogger());
    }

    /**
     * @dataProvider provideLevelsAndMessages
     */
    public function testLogsAtAllLevels($level, $message)
    {
        $logger = $this->getLogger();
        $logger->{$level}($message, array('user' => 'Bob'));
        $logger->log($level, $message, array('user' => 'Bob'));

        $expected = array(
            "$level message of level $level with context: Bob",
            "$level message of level $level with context: Bob",
        );
        $this->assertEquals($expected, $this->getLogs());
    }

    public function provideLevelsAndMessages()
    {
        return array(
            LogLevel::EMERGENCY => array(LogLevel::EMERGENCY, 'message of level emergency with context: {user}'),
            LogLevel::ALERT => array(LogLevel::ALERT, 'message of level alert with context: {user}'),
            LogLevel::CRITICAL => array(LogLevel::CRITICAL, 'message of level critical with context: {user}'),
            LogLevel::ERROR => array(LogLevel::ERROR, 'message of level error with context: {user}'),
            LogLevel::WARNING => array(LogLevel::WARNING, 'message of level warning with context: {user}'),
            LogLevel::NOTICE => array(LogLevel::NOTICE, 'message of level notice with context: {user}'),
            LogLevel::INFO => array(LogLevel::INFO, 'message of level info with context: {user}'),
            LogLevel::DEBUG => array(LogLevel::DEBUG, 'message of level debug with context: {user}'),
        );
    }

    public function testThrowsOnInvalidLevel()
    {
        $logger = $this->getLogger();

        $this->expectException(InvalidArgumentException::class);
        $logger->log('invalid level', 'Foo');
    }

    public function testContextReplacement()
    {
        $logger = $this->getLogger();
        $logger->info('{Message {nothing} {user} {foo.bar} a}', array('user' => 'Bob', 'foo.bar' => 'Bar'));

        $expected = array('info {Message {nothing} Bob Bar a}');
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testObjectCastToString()
    {
        $string = uniqid('DUMMY');
        $dummy = $this->createStringable($string);
        $dummy->expects($this->once())
            ->method('__toString');

        $this->getLogger()->warning($dummy);

        $expected = array("warning $string");
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testContextCanContainAnything()
    {
        $closed = fopen('php://memory', 'r');
        fclose($closed);

        $context = array(
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => array('with object' => $this->createStringable()),
            'object' => new \DateTime('now', new DateTimeZone('Europe/London')),
            'resource' => fopen('php://memory', 'r'),
            'closed' => $closed,
        );

        $this->getLogger()->warning('Crazy context data', $context);

        $expected = array('warning Crazy context data');
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues()
    {
        $logger = $this->getLogger();
        $logger->warning('Random message', array('exception' => 'oops'));
        $logger->critical('Uncaught Exception!', array('exception' => new \LogicException('Fail')));

        $expected = array(
            'warning Random message',
            'critical Uncaught Exception!'
        );
        $this->assertEquals($expected, $this->getLogs());
    }

    /**
     * Creates a mock of a `Stringable`.
     *
     * @param string $string The string that must be represented by the stringable.
     * @return \PHPUnit_Framework_MockObject_MockObject A mock of an object that has a `__toString()` method.
     */
    protected function createStringable($string = '')
    {
        $mock = $this->getMockBuilder('Stringable')
            ->setMethods(array('__toString'))
            ->getMock();

        $mock->method('__toString')
            ->will($this->returnValue($string));

        return $mock;
    }
}
