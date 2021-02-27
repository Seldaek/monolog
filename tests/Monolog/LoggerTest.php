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

use Monolog\Processor\WebProcessor;
use Monolog\Handler\TestHandler;

class LoggerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Monolog\Logger::getName
     */
    public function testGetName()
    {
        $logger = new Logger('foo');
        $this->assertEquals('foo', $logger->getName());
    }

    /**
     * @covers Monolog\Logger::getLevelName
     */
    public function testGetLevelName()
    {
        $this->assertEquals('ERROR', Logger::getLevelName(Logger::ERROR));
    }

    /**
     * @covers Monolog\Logger::withName
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
     * @covers Monolog\Logger::toMonologLevel
     */
    public function testConvertPSR3ToMonologLevel()
    {
        $this->assertEquals(Logger::toMonologLevel('debug'), 100);
        $this->assertEquals(Logger::toMonologLevel('info'), 200);
        $this->assertEquals(Logger::toMonologLevel('notice'), 250);
        $this->assertEquals(Logger::toMonologLevel('warning'), 300);
        $this->assertEquals(Logger::toMonologLevel('error'), 400);
        $this->assertEquals(Logger::toMonologLevel('critical'), 500);
        $this->assertEquals(Logger::toMonologLevel('alert'), 550);
        $this->assertEquals(Logger::toMonologLevel('emergency'), 600);
    }

    /**
     * @covers Monolog\Logger::getLevelName
     */
    public function testGetLevelNameThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        Logger::getLevelName(5);
    }

    /**
     * @covers Monolog\Logger::__construct
     */
    public function testChannel()
    {
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->warning('test');
        list($record) = $handler->getRecords();
        $this->assertEquals('foo', $record['channel']);
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLog()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->prophesize('Monolog\Handler\NullHandler');
        $handler->handle(\Prophecy\Argument::any())->shouldBeCalled();
        $handler->isHandling(['level' => 300])->willReturn(true);

        $logger->pushHandler($handler->reveal());

        $this->assertTrue($logger->addRecord(Logger::WARNING, 'test'));
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testLogNotHandled()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->prophesize('Monolog\Handler\NullHandler');
        $handler->handle()->shouldNotBeCalled();
        $handler->isHandling(['level' => 300])->willReturn(false);

        $logger->pushHandler($handler->reveal());

        $this->assertFalse($logger->addRecord(Logger::WARNING, 'test'));
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
     * @covers Monolog\Logger::pushHandler
     * @covers Monolog\Logger::popHandler
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
     * @covers Monolog\Logger::setHandlers
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
     * @covers Monolog\Logger::pushProcessor
     * @covers Monolog\Logger::popProcessor
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
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsAreExecuted()
    {
        $logger = new Logger(__METHOD__);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->pushProcessor(function ($record) {
            $record['extra']['win'] = true;

            return $record;
        });
        $logger->error('test');
        list($record) = $handler->getRecords();
        $this->assertTrue($record['extra']['win']);
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsAreCalledOnlyOnce()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler->expects($this->any())
            ->method('handle')
            ->will($this->returnValue(true))
        ;
        $logger->pushHandler($handler);

        $processor = $this->getMockBuilder('Monolog\Processor\WebProcessor')
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock()
        ;
        $processor->expects($this->once())
            ->method('__invoke')
            ->will($this->returnArgument(0))
        ;
        $logger->pushProcessor($processor);

        $logger->error('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testProcessorsNotCalledWhenNotHandled()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler);
        $that = $this;
        $logger->pushProcessor(function ($record) use ($that) {
            $that->fail('The processor should not be called');
        });
        $logger->alert('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testHandlersNotCalledBeforeFirstHandling()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->never())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler1->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler2);

        $handler3 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler3->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler3->expects($this->never())
            ->method('handle')
        ;
        $logger->pushHandler($handler3);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testHandlersNotCalledBeforeFirstHandlingWithAssocArray()
    {
        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->never())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler1->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;

        $handler3 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler3->expects($this->once())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;
        $handler3->expects($this->never())
            ->method('handle')
        ;

        $logger = new Logger(__METHOD__, ['last' => $handler3, 'second' => $handler2, 'first' => $handler1]);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testBubblingWhenTheHandlerReturnsFalse()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler1->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(false))
        ;
        $logger->pushHandler($handler2);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::addRecord
     */
    public function testNotBubblingWhenTheHandlerReturnsTrue()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler1->expects($this->never())
            ->method('handle')
        ;
        $logger->pushHandler($handler1);

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler2->expects($this->once())
            ->method('handle')
            ->will($this->returnValue(true))
        ;
        $logger->pushHandler($handler2);

        $logger->debug('test');
    }

    /**
     * @covers Monolog\Logger::isHandling
     */
    public function testIsHandling()
    {
        $logger = new Logger(__METHOD__);

        $handler1 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler1->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(false))
        ;

        $logger->pushHandler($handler1);
        $this->assertFalse($logger->isHandling(Logger::DEBUG));

        $handler2 = $this->createMock('Monolog\Handler\HandlerInterface');
        $handler2->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;

        $logger->pushHandler($handler2);
        $this->assertTrue($logger->isHandling(Logger::DEBUG));
    }

    /**
     * @dataProvider logMethodProvider
     * @covers Monolog\Logger::debug
     * @covers Monolog\Logger::info
     * @covers Monolog\Logger::notice
     * @covers Monolog\Logger::warning
     * @covers Monolog\Logger::error
     * @covers Monolog\Logger::critical
     * @covers Monolog\Logger::alert
     * @covers Monolog\Logger::emergency
     */
    public function testLogMethods($method, $expectedLevel)
    {
        $logger = new Logger('foo');
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->{$method}('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($expectedLevel, $record['level']);
    }

    public function logMethodProvider()
    {
        return [
            // PSR-3 methods
            ['debug',  Logger::DEBUG],
            ['info',   Logger::INFO],
            ['notice', Logger::NOTICE],
            ['warning',   Logger::WARNING],
            ['error',    Logger::ERROR],
            ['critical',   Logger::CRITICAL],
            ['alert',  Logger::ALERT],
            ['emergency',  Logger::EMERGENCY],
        ];
    }

    /**
     * @dataProvider setTimezoneProvider
     * @covers Monolog\Logger::setTimezone
     */
    public function testSetTimezone($tz)
    {
        $logger = new Logger('foo');
        $logger->setTimezone($tz);
        $handler = new TestHandler;
        $logger->pushHandler($handler);
        $logger->info('test');
        list($record) = $handler->getRecords();
        $this->assertEquals($tz, $record['datetime']->getTimezone());
    }

    public function setTimezoneProvider()
    {
        return array_map(
            function ($tz) {
                return [new \DateTimeZone($tz)];
            },
            \DateTimeZone::listIdentifiers()
        );
    }

    /**
     * @covers Monolog\Logger::setTimezone
     * @covers Monolog\DateTimeImmutable::__construct
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

            $this->assertEquals($tz, $record['datetime']->getTimezone());
            $this->assertEquals($dt->format('Y/m/d H:i'), $record['datetime']->format('Y/m/d H:i'), 'Time should match timezone with microseconds set to: '.var_export($microseconds, true));
        }
    }

    /**
     * @covers Monolog\Logger::setTimezone
     * @covers Monolog\DateTimeImmutable::__construct
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

            $this->assertEquals($tz, $record['datetime']->getTimezone());
            $this->assertEquals($dt->format('Y/m/d H:i'), $record['datetime']->format('Y/m/d H:i'), 'Time should match timezone with microseconds set to: '.var_export($microseconds, true));
        }
    }

    public function tearDown(): void
    {
        date_default_timezone_set('UTC');
    }

    /**
     * @dataProvider useMicrosecondTimestampsProvider
     * @covers Monolog\Logger::useMicrosecondTimestamps
     * @covers Monolog\Logger::addRecord
     */
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
        $this->{$assert}('000000', $record['datetime']->format('u'));
        $this->assertSame($record['datetime']->format($assertFormat), (string) $record['datetime']);
    }

    public function useMicrosecondTimestampsProvider()
    {
        return [
            // this has a very small chance of a false negative (1/10^6)
            'with microseconds' => [true, 'assertNotSame', 'Y-m-d\TH:i:s.uP'],
            // php 7.1 always includes microseconds, so we keep them in, but we format the datetime without
            'without microseconds' => [false, 'assertNotSame', 'Y-m-d\TH:i:sP'],
        ];
    }

    /**
     * @covers Monolog\Logger::setExceptionHandler
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
     * @covers Monolog\Logger::handleException
     */
    public function testDefaultHandleException()
    {
        $logger = new Logger(__METHOD__);
        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler->expects($this->any())
            ->method('handle')
            ->will($this->throwException(new \Exception('Some handler exception')))
        ;

        $this->expectException(\Exception::class);

        $logger->pushHandler($handler);
        $logger->info('test');
    }

    /**
     * @covers Monolog\Logger::handleException
     * @covers Monolog\Logger::addRecord
     */
    public function testCustomHandleException()
    {
        $logger = new Logger(__METHOD__);
        $that = $this;
        $logger->setExceptionHandler(function ($e, $record) use ($that) {
            $that->assertEquals($e->getMessage(), 'Some handler exception');
            $that->assertTrue(is_array($record));
            $that->assertEquals($record['message'], 'test');
        });
        $handler = $this->getMockBuilder('Monolog\Handler\HandlerInterface')->getMock();
        $handler->expects($this->any())
            ->method('isHandling')
            ->will($this->returnValue(true))
        ;
        $handler->expects($this->any())
            ->method('handle')
            ->will($this->throwException(new \Exception('Some handler exception')))
        ;
        $logger->pushHandler($handler);
        $logger->info('test');
    }

    public function testReset()
    {
        $logger = new Logger('app');

        $testHandler = new Handler\TestHandler();
        $testHandler->setSkipReset(true);
        $bufferHandler = new Handler\BufferHandler($testHandler);
        $groupHandler = new Handler\GroupHandler(array($bufferHandler));
        $fingersCrossedHandler = new Handler\FingersCrossedHandler($groupHandler);

        $logger->pushHandler($fingersCrossedHandler);

        $processorUid1 = new Processor\UidProcessor(10);
        $uid1 = $processorUid1->getUid();
        $groupHandler->pushProcessor($processorUid1);

        $processorUid2 = new Processor\UidProcessor(5);
        $uid2 = $processorUid2->getUid();
        $logger->pushProcessor($processorUid2);

        $getProperty = function ($object, $property) {
            $reflectionProperty = new \ReflectionProperty(get_class($object), $property);
            $reflectionProperty->setAccessible(true);

            return $reflectionProperty->getValue($object);
        };
        $that = $this;
        $assertBufferOfBufferHandlerEmpty = function () use ($getProperty, $bufferHandler, $that) {
            $that->assertEmpty($getProperty($bufferHandler, 'buffer'));
        };
        $assertBuffersEmpty = function () use ($assertBufferOfBufferHandlerEmpty, $getProperty, $fingersCrossedHandler, $that) {
            $assertBufferOfBufferHandlerEmpty();
            $that->assertEmpty($getProperty($fingersCrossedHandler, 'buffer'));
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
        $this->assertTrue($testHandler->hasRecordThatContains('debug2', Logger::DEBUG));
        $this->assertTrue($testHandler->hasRecordThatContains('error2', Logger::ERROR));
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
        $this->assertTrue($testHandler->hasRecordThatContains('notice4', Logger::NOTICE));
        $this->assertTrue($testHandler->hasRecordThatContains('emergency4', Logger::EMERGENCY));
        $this->assertNotSame($uid1, $processorUid1->getUid());
        $this->assertNotSame($uid2, $processorUid2->getUid());
    }
}
