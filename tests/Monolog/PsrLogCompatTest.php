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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

class PsrLogCompatTest extends TestCase
{
    private TestHandler $handler;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->handler);
    }

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

            return preg_replace_callback('{^[A-Z]+}', $lower, $record->formatted);
        };

        return array_map($convert, $this->handler->getRecords());
    }

    public function testImplements()
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->getLogger());
    }

    #[DataProvider('provideLevelsAndMessages')]
    public function testLogsAtAllLevels($level, $message)
    {
        $logger = $this->getLogger();
        $logger->{$level}($message, ['user' => 'Bob']);
        $logger->log($level, $message, ['user' => 'Bob']);

        $expected = [
            "$level message of level $level with context: Bob",
            "$level message of level $level with context: Bob",
        ];
        $this->assertEquals($expected, $this->getLogs());
    }

    public static function provideLevelsAndMessages()
    {
        return [
            LogLevel::EMERGENCY => [LogLevel::EMERGENCY, 'message of level emergency with context: {user}'],
            LogLevel::ALERT => [LogLevel::ALERT, 'message of level alert with context: {user}'],
            LogLevel::CRITICAL => [LogLevel::CRITICAL, 'message of level critical with context: {user}'],
            LogLevel::ERROR => [LogLevel::ERROR, 'message of level error with context: {user}'],
            LogLevel::WARNING => [LogLevel::WARNING, 'message of level warning with context: {user}'],
            LogLevel::NOTICE => [LogLevel::NOTICE, 'message of level notice with context: {user}'],
            LogLevel::INFO => [LogLevel::INFO, 'message of level info with context: {user}'],
            LogLevel::DEBUG => [LogLevel::DEBUG, 'message of level debug with context: {user}'],
        ];
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
        $logger->info('{Message {nothing} {user} {foo.bar} a}', ['user' => 'Bob', 'foo.bar' => 'Bar']);

        $expected = ['info {Message {nothing} Bob Bar a}'];
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testObjectCastToString()
    {
        $string = uniqid('DUMMY');
        $dummy = $this->createStringable($string);
        $dummy->expects($this->once())
            ->method('__toString');

        $this->getLogger()->warning($dummy);

        $expected = ["warning $string"];
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testContextCanContainAnything()
    {
        $closed = fopen('php://memory', 'r');
        fclose($closed);

        $context = [
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => ['with object' => $this->createStringable()],
            'object' => new \DateTime('now', new DateTimeZone('Europe/London')),
            'resource' => fopen('php://memory', 'r'),
            'closed' => $closed,
        ];

        $this->getLogger()->warning('Crazy context data', $context);

        $expected = ['warning Crazy context data'];
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues()
    {
        $logger = $this->getLogger();
        $logger->warning('Random message', ['exception' => 'oops']);
        $logger->critical('Uncaught Exception!', ['exception' => new \LogicException('Fail')]);

        $expected = [
            'warning Random message',
            'critical Uncaught Exception!',
        ];
        $this->assertEquals($expected, $this->getLogs());
    }

    /**
     * Creates a mock of a `Stringable`.
     *
     * @param  string $string The string that must be represented by the stringable.
     */
    protected function createStringable(string $string = ''): MockObject&Stringable
    {
        $mock = $this->getMockBuilder(Stringable::class)
            ->getMock();

        $mock->method('__toString')
            ->willReturn($string);

        return $mock;
    }
}
