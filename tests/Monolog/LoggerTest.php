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

use Monolog\Handler\HandlerInterface;
use Monolog\Processor\WebProcessor;
use Monolog\Handler\TestHandler;
use Monolog\Test\MonologTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class LoggerTest extends MonologTestCase
{
    /**
     * @covers Logger::getName
     */
    public function testGetName()
    {
        $logger = new Logger('foo');
        $this->assertEquals('foo', $logger->getName());
    }

    /**
     * @covers Logger::withName
     */
    public function testWithName()
    {
        $first = new Logger('first', [$handler = new TestHandler()]);
        $second = $first->withName('second');

        $this->assertSame('first', $first->getName());
        $this->assertSame('second', $second->getName());
        $this->assertSame($handler, $second->popHandler());
    }

    /**
     * @covers Logger::toMonologLevel
     */
    public function testConvertPSR3ToMonologLevel()
    {
        $this->assertEquals(Logger::toMonologLevel('debug'), Level::Debug);
        $this->assertEquals(Logger::toMonologLevel('info'), Level::Info);
        $this->assertEquals(Logger::toMonologLevel('notice'), Level::Notice);
        $this->assertEquals(Logger::toMonologLevel('warning'), Level::Warning);
        $this->assertEquals(Logger::toMonologLevel('error'), Level::Error);
        $this->assertEquals(Logger::toMonologLevel('critical'), Level::Critical);
        $this->assertEquals(Logger::toMonologLevel('alert'), Level::Alert);
        $this->assertEquals(Logger::toMonologLevel('emergency'), Level::Emergency);
    }

    /**
     * @covers Monolog\Logger::addRecord
     * @covers Monolog\Logger::log
     */
    public function testConvertRFC5424ToMonologLevelInAddRecordAndLog()
    {
        $logger = new Logger('test');
        $handler = new TestHandler;
        $logger->pushHandler($handler);

        foreach ([
            7 => 100,
            6 => 200,
            5 => 250,
            4 => 300,
            3 => 400,
            2 => 500,
            1 => 550,
            0 => 600,
        ] as $rfc5424Level => $monologLevel) {
            $handler->reset();
            $logger->addRecord($rfc5424Level, 'test');
            $logger->log($rfc5424Level, 'test');
            $records = $handler->getRecords();

            self::assertCount(2, $records);
            self::assertSame($monologLevel, $records[0]['level']);
            self::assertSame($monologLevel, $records[1]['level']);
        }
    }

    /**
     * @covers Logger::__construct
     */
    public function testChannel()
    {
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->warning('test');
        list($record) = $handler->getRecords();
        $this->assertEquals('foo', $record->channel);
    }

    /**
     * @covers Logger::addRecord
     */
    public function testLogPreventsCircularLogging()
    {
        $logger = new Logger(__METHOD__);

        $loggingHandler = new LoggingHandler($logger);
        $testHandler = new TestHandler();

        $logger->pushHandler($loggingHandler);
        $logger->pushHandler($testHandler);

        $logger->addRecord(Level::Alert, 'test');

        $records = $testHandler->getRecords();
        $this->assertCount(3, $records);
        $this->assertSame('ALERT', $records[0]->level->getName());
        $this->assertSame('DEBUG', $records[1]->level->getName());
        $this->assertSame('WARNING', $records[2]->level->getName());
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLog()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->never())->method('isHandling');
        $handler->expects($this->once())->method('handle');

        $logger->pushHandler($handler);

        $this->assertTrue($logger->addRecord(Level::Warning, 'test'));
    }

    /**
     * @covers Logger::addRecord
     */
    public function testLogAlwaysHandledIfNoProcessorsArePresent()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->never())->method('isHandling');
        $handler->expects($this->once())->method('handle');

        $logger->pushHandler($handler);

        $this->assertTrue($logger->addRecord(Level::Warning, 'test'));
    }

    /**
     * @covers Logger::addRecord
     */
    public function testLogNotHandledIfProcessorsArePresent()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->once())->method('isHandling')->willReturn(false);
        $handler->expects($this->never())->method('handle');

        $logger->pushProcessor(fn (LogRecord $record) => $record);
        $logger->pushHandler($handler);

        $this->assertFalse($logger->addRecord(Level::Warning, 'test'));
    }

    public function testHandlersInCtor()
    {
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;
        $logger = new Logger(__METHOD__, [$handler1, $handler2]);

        $this->assertEquals($handler1, $logger->popHandler());
        $this->assertEquals($handler2, $logger->popHandler());
    }

    public function testProcessorsInCtor()
    {
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;
        $logger = new Logger(__METHOD__, [], [$processor1, $processor2]);

        $this->assertEquals($processor1, $logger->popProcessor());
        $this->assertEquals($processor2, $logger->popProcessor());
    }

    /**
     * @covers Logger::pushHandler
     * @covers Logger::popHandler
     */
    public function testPushPopHandler()
    {
        $logger = new Logger(__METHOD__);
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;

        $logger->pushHandler($handler1);
        $logger->pushHandler($handler2);

        $this->assertEquals($handler2, $logger->popHandler());
        $this->assertEquals($handler1, $logger->popHandler());

        $this->expectException(\LogicException::class);

        $logger->popHandler();
    }

    /**
     * @covers Logger::setHandlers
     */
    public function testSetHandlers()
    {
        $logger = new Logger(__METHOD__);
        $handler1 = new TestHandler;
        $handler2 = new TestHandler;

        $logger->pushHandler($handler1);
        $logger->setHandlers([$handler2]);

        // handler1 has been removed
        $this->assertEquals([$handler2], $logger->getHandlers());

        $logger->setHandlers([
            "AMapKey" => $handler1,
            "Woop" => $handler2,
        ]);

        // Keys have been scrubbed
        $this->assertEquals([$handler1, $handler2], $logger->getHandlers());
    }

    /**
     * @covers Logger::pushProcessor
     * @covers Logger::popProcessor
     */
    public function testPushPopProcessor()
    {
        $logger = new Logger(__METHOD__);
        $processor1 = new WebProcessor;
        $processor2 = new WebProcessor;

        $logger->pushProcessor($processor1);
        $logger->pushProcessor($processor2);

        $this->assertEquals($processor2, $logger->popProcessor());
        $this->assertEquals($processor1, $logger->popProcessor());

        $this->expectException(\LogicException::class);

        $logger->popProcessor();
    }

    /**
     * @covers Logger::addRecord
     */
    public function testProcessorsAreExecuted()
    {
        $logger = new Logger(__METHOD__);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->pushProcessor(function ($record) {
            $record->extra['win'] = true;

            return $record;
        });
        $logger->error('test');
        list($record) = $handler->getRecords();
        $this->assertTrue($record->extra['win']);
    }

    /**
     * @covers Logger::addRecord
     */
    public function testProcessorsAreCalledOnlyOnce()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler->expects($this->any())
            ->method('handle')
            ->willReturn(true);

        $logger->pushHandler($handler);

        $processor = $this->getMockBuilder('Monolog\Processor\WebProcessor')
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock()
        ;
        $processor->expects($this->once())
            ->method('__invoke')
            ->willReturnArgument(0)
        ;
        $logger->pushProcessor($processor);

        $logger->error('test');
    }

    /**
     * @covers Logger::addRecord
     */
    public function testProcessorsNotCalledWhenNotHandled()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler->expects($this->once())
            ->method('isHandling')
            ->willReturn(false);

        $logger->pushHandler($handler);
        $that = $this;
        $logger->pushProcessor(function ($record) use ($that) {
            $that->fail('The processor should not be called');
        });
        $logger->alert('test');
    }

    /**
     * @covers Logger::addRecord
     */
    public function testHandlersNotCalledBeforeFirstHandlingWhenProcessorsPresent()
    {
        $logger = new Logger(__METHOD__);
        $logger->pushProcessor(fn ($record) => $record);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->never())
            ->method('isHandling')
            ->willReturn(false);

        $handler1->expects($this->once())
            ->method('handle')
            ->willReturn(false);

        $logger->pushHandler($handler1);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->once())
            ->method('isHandling')
            ->willReturn(true);

        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn(false);

        $logger->pushHandler($handler2);

        $handler3 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler3->expects($this->once())
            ->method('isHandling')
            ->willReturn(false);

        $handler3->expects($this->never())
            ->method('handle')
        ;
        $logger->pushHandler($handler3);

        $logger->debug('test');
    }

    /**
     * @covers Logger::addRecord
     */
    public function testHandlersNotCalledBeforeFirstHandlingWhenProcessorsPresentWithAssocArray()
    {
        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->never())
            ->method('isHandling')
            ->willReturn(false);

        $handler1->expects($this->once())
            ->method('handle')
            ->willReturn(false);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->once())
            ->method('isHandling')
            ->willReturn(true);

        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn(false);

        $handler3 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler3->expects($this->once())
            ->method('isHandling')
            ->willReturn(false);

        $handler3->expects($this->never())
            ->method('handle')
        ;

        $logger = new Logger(__METHOD__, ['last' => $handler3, 'second' => $handler2, 'first' => $handler1]);
        $logger->pushProcessor(fn ($record) => $record);

        $logger->debug('test');
    }

    /**
     * @covers Logger::addRecord
     */
    public function testBubblingWhenTheHandlerReturnsFalse()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler1->expects($this->once())
            ->method('handle')
            ->willReturn(false);

        $logger->pushHandler($handler1);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn(false);

        $logger->pushHandler($handler2);

        $logger->debug('test');
    }

    /**
     * @covers Logger::addRecord
     */
    public function testNotBubblingWhenTheHandlerReturnsTrue()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler1->expects($this->never())
            ->method('handle')
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn(true);

        $logger->pushHandler($handler2);

        $logger->debug('test');
    }

    /**
     * @covers Logger::isHandling
     */
    public function testIsHandling()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->willReturn(false);

        $logger->pushHandler($handler1);
        $this->assertFalse($logger->isHandling(Level::Debug));

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $logger->pushHandler($handler2);
        $this->assertTrue($logger->isHandling(Level::Debug));
    }

    /**
     * @covers Level::Debug
     * @covers Level::Info
     * @covers Level::Notice
     * @covers Level::Warning
     * @covers Level::Error
     * @covers Level::Critical
     * @covers Level::Alert
     * @covers Level::Emergency
     */
    #[DataProvider('logMethodProvider')]
    public function testLogMethods(string $method, Level $expectedLevel)
    {
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->{$method}('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($expectedLevel, $record->level);
    }

    public static function logMethodProvider()
    {
        return [
            // PSR-3 methods
            ['debug',  Level::Debug],
            ['info',   Level::Info],
            ['notice', Level::Notice],
            ['warning',   Level::Warning],
            ['error',    Level::Error],
            ['critical',   Level::Critical],
            ['alert',  Level::Alert],
            ['emergency',  Level::Emergency],
        ];
    }

    /**
     * @covers Logger::setTimezone
     */
    #[DataProvider('setTimezoneProvider')]
    public function testSetTimezone($tz)
    {
        $logger = new Logger('foo');
        $logger->setTimezone($tz);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->info('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($tz, $record->datetime->getTimezone());
    }

    public static function setTimezoneProvider()
    {
        return array_map(
            function ($tz) {
                return [new \DateTimeZone($tz)];
            },
            \DateTimeZone::listIdentifiers()
        );
    }

    /**
     * @covers Logger::setTimezone
     * @covers JsonSerializableDateTimeImmutable::__construct
     */
    public function testTimezoneIsRespectedInUTC()
    {
        foreach ([true, false] as $microseconds) {
            $logger = new Logger('foo');
            $logger->useMicrosecondTimestamps($microseconds);
            $tz = new \DateTimeZone('America/New_York');
            $logger->setTimezone($tz);
            $handler = new TestHandler;
            $logger->pushHandler($handler);
            $dt = new \DateTime('now', $tz);
            $logger->info('test');
            list($record) = $handler->getRecords();

            $this->assertEquals($tz, $record->datetime->getTimezone());
            $this->assertEquals($dt->format('Y/m/d H:i'), $record->datetime->format('Y/m/d H:i'), 'Time should match timezone with microseconds set to: '.var_export($microseconds, true));
        }
    }

    /**
     * @covers Logger::setTimezone
     * @covers JsonSerializableDateTimeImmutable::__construct
     */
    public function testTimezoneIsRespectedInOtherTimezone()
    {
        date_default_timezone_set('CET');
        foreach ([true, false] as $microseconds) {
            $logger = new Logger('foo');
            $logger->useMicrosecondTimestamps($microseconds);
            $tz = new \DateTimeZone('America/New_York');
            $logger->setTimezone($tz);
            $handler = new TestHandler;
            $logger->pushHandler($handler);
            $dt = new \DateTime('now', $tz);
            $logger->info('test');
            list($record) = $handler->getRecords();

            $this->assertEquals($tz, $record->datetime->getTimezone());
            $this->assertEquals($dt->format('Y/m/d H:i'), $record->datetime->format('Y/m/d H:i'), 'Time should match timezone with microseconds set to: '.var_export($microseconds, true));
        }
    }

    public function tearDown(): void
    {
        date_default_timezone_set('UTC');
    }

    /**
     * @covers Logger::useMicrosecondTimestamps
     * @covers Logger::addRecord
     */
    #[DataProvider('useMicrosecondTimestampsProvider')]
    public function testUseMicrosecondTimestamps($micro, $assert, $assertFormat)
    {
        if (PHP_VERSION_ID === 70103) {
            $this->markTestSkipped();
        }

        $logger = new Logger('foo');
        $logger->useMicrosecondTimestamps($micro);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->info('test');
        list($record) = $handler->getRecords();
        $this->{$assert}('000000', $record->datetime->format('u'));
        $this->assertSame($record->datetime->format($assertFormat), (string) $record->datetime);
    }

    public static function useMicrosecondTimestampsProvider()
    {
        return [
            // this has a very small chance of a false negative (1/10^6)
            'with microseconds' => [true, 'assertNotSame', 'Y-m-d\TH:i:s.uP'],
            // php 7.1 always includes microseconds, so we keep them in, but we format the datetime without
            'without microseconds' => [false, 'assertNotSame', 'Y-m-d\TH:i:sP'],
        ];
    }

    public function testProcessorsDoNotInterfereBetweenHandlers()
    {
        $logger = new Logger('foo');
        $logger->pushHandler($t1 = new TestHandler());
        $logger->pushHandler($t2 = new TestHandler());
        $t1->pushProcessor(function (LogRecord $record) {
            $record->extra['foo'] = 'bar';

            return $record;
        });
        $logger->error('Foo');

        self::assertSame([], $t2->getRecords()[0]->extra);
    }

    /**
     * @covers Logger::setExceptionHandler
     */
    public function testSetExceptionHandler()
    {
        $logger = new Logger(__METHOD__);
        $this->assertNull($logger->getExceptionHandler());
        $callback = function ($ex) {
        };
        $logger->setExceptionHandler($callback);
        $this->assertEquals($callback, $logger->getExceptionHandler());
    }

    /**
     * @covers Logger::handleException
     */
    public function testDefaultHandleException()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler->expects($this->any())
            ->method('handle')
            ->will($this->throwException(new \Exception('Some handler exception')))
        ;

        $this->expectException(\Exception::class);

        $logger->pushHandler($handler);
        $logger->info('test');
    }

    /**
     * @covers Logger::handleException
     * @covers Logger::addRecord
     */
    public function testCustomHandleException()
    {
        $logger = new Logger(__METHOD__);
        $that = $this;
        $logger->setExceptionHandler(function ($e, $record) use ($that) {
            $that->assertEquals($e->getMessage(), 'Some handler exception');
            $that->assertInstanceOf(LogRecord::class, $record);
            $that->assertEquals($record->message, 'test');
        });
        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->any())
            ->method('isHandling')
            ->willReturn(true);

        $handler->expects($this->any())
            ->method('handle')
            ->will($this->throwException(new \Exception('Some handler exception')))
        ;
        $logger->pushHandler($handler);
        $logger->info('test');
    }

    public function testSerializable()
    {
        $logger = new Logger(__METHOD__);
        $copy = unserialize(serialize($logger));
        self::assertInstanceOf(Logger::class, $copy);
        self::assertSame($logger->getName(), $copy->getName());
        self::assertSame($logger->getTimezone()->getName(), $copy->getTimezone()->getName());
        self::assertSame($logger->getHandlers(), $copy->getHandlers());
    }

    public function testReset()
    {
        $logger = new Logger('app');

        $testHandler = new Handler\TestHandler();
        $testHandler->setSkipReset(true);
        $bufferHandler = new Handler\BufferHandler($testHandler);
        $groupHandler = new Handler\GroupHandler([$bufferHandler]);
        $fingersCrossedHandler = new Handler\FingersCrossedHandler($groupHandler);

        $logger->pushHandler($fingersCrossedHandler);

        $processorUid1 = new Processor\UidProcessor(10);
        $uid1 = $processorUid1->getUid();
        $groupHandler->pushProcessor($processorUid1);

        $processorUid2 = new Processor\UidProcessor(5);
        $uid2 = $processorUid2->getUid();
        $logger->pushProcessor($processorUid2);

        $getProperty = function ($object, $property) {
            $reflectionProperty = new \ReflectionProperty(\get_class($object), $property);

            return $reflectionProperty->getValue($object);
        };
        $assertBufferOfBufferHandlerEmpty = function () use ($getProperty, $bufferHandler) {
            self::assertEmpty($getProperty($bufferHandler, 'buffer'));
        };
        $assertBuffersEmpty = function () use ($assertBufferOfBufferHandlerEmpty, $getProperty, $fingersCrossedHandler) {
            $assertBufferOfBufferHandlerEmpty();
            self::assertEmpty($getProperty($fingersCrossedHandler, 'buffer'));
        };

        $logger->debug('debug1');
        $logger->reset();
        $assertBuffersEmpty();
        $this->assertFalse($testHandler->hasDebugRecords());
        $this->assertFalse($testHandler->hasErrorRecords());
        $this->assertNotSame($uid1, $uid1 = $processorUid1->getUid());
        $this->assertNotSame($uid2, $uid2 = $processorUid2->getUid());

        $logger->debug('debug2');
        $logger->error('error2');
        $logger->reset();
        $assertBuffersEmpty();
        $this->assertTrue($testHandler->hasRecordThatContains('debug2', Level::Debug));
        $this->assertTrue($testHandler->hasRecordThatContains('error2', Level::Error));
        $this->assertNotSame($uid1, $uid1 = $processorUid1->getUid());
        $this->assertNotSame($uid2, $uid2 = $processorUid2->getUid());

        $logger->info('info3');
        $this->assertNotEmpty($getProperty($fingersCrossedHandler, 'buffer'));
        $assertBufferOfBufferHandlerEmpty();
        $this->assertFalse($testHandler->hasInfoRecords());

        $logger->reset();
        $assertBuffersEmpty();
        $this->assertFalse($testHandler->hasInfoRecords());
        $this->assertNotSame($uid1, $uid1 = $processorUid1->getUid());
        $this->assertNotSame($uid2, $uid2 = $processorUid2->getUid());

        $logger->notice('notice4');
        $logger->emergency('emergency4');
        $logger->reset();
        $assertBuffersEmpty();
        $this->assertFalse($testHandler->hasInfoRecords());
        $this->assertTrue($testHandler->hasRecordThatContains('notice4', Level::Notice));
        $this->assertTrue($testHandler->hasRecordThatContains('emergency4', Level::Emergency));
        $this->assertNotSame($uid1, $processorUid1->getUid());
        $this->assertNotSame($uid2, $processorUid2->getUid());
    }

    /**
     * @covers Logger::addRecord
     */
    public function testLogWithDateTime()
    {
        foreach ([true, false] as $microseconds) {
            $logger = new Logger(__METHOD__);

            $loggingHandler = new LoggingHandler($logger);
            $testHandler = new TestHandler();

            $logger->pushHandler($loggingHandler);
            $logger->pushHandler($testHandler);

            $datetime = (new JsonSerializableDateTimeImmutable($microseconds))->modify('2022-03-04 05:06:07');
            $logger->addRecord(Level::Debug, 'test', [], $datetime);

            list($record) = $testHandler->getRecords();
            $this->assertEquals($datetime->format('Y-m-d H:i:s'), $record->datetime->format('Y-m-d H:i:s'));
        }
    }

    public function testLogCycleDetectionWithFibersWithoutCycle()
    {
        $logger = new Logger(__METHOD__);

        $fiberSuspendHandler = new FiberSuspendHandler();
        $testHandler = new TestHandler();

        $logger->pushHandler($fiberSuspendHandler);
        $logger->pushHandler($testHandler);

        $fibers = [];
        for ($i = 0; $i < 10; $i++) {
            $fiber = new \Fiber(static function () use ($logger) {
                $logger->info('test');
            });

            $fiber->start();

            // We need to keep a reference here, because otherwise the fiber gets automatically cleaned up
            $fibers[] = $fiber;
        }

        self::assertCount(10, $testHandler->getRecords());
    }

    public function testLogCycleDetectionWithFibersWithCycle()
    {
        $logger = new Logger(__METHOD__);

        $fiberSuspendHandler = new FiberSuspendHandler();
        $loggingHandler = new LoggingHandler($logger);
        $testHandler = new TestHandler();

        $logger->pushHandler($fiberSuspendHandler);
        $logger->pushHandler($loggingHandler);
        $logger->pushHandler($testHandler);

        $fiber = new \Fiber(static function () use ($logger) {
            $logger->info('test');
        });

        $fiber->start();

        self::assertCount(3, $testHandler->getRecords());
    }
}

class LoggingHandler implements HandlerInterface
{
    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        $this->logger->debug('Log triggered while logging');

        return false;
    }

    public function handleBatch(array $records): void
    {
    }

    public function close(): void
    {
    }
}

class FiberSuspendHandler implements HandlerInterface
{
    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        \Fiber::suspend();

        return true;
    }

    public function handleBatch(array $records): void
    {
    }

    public function close(): void
    {
    }
}
