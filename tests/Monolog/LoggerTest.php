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

class LoggerTest extends \PHPUnit_Framework_TestCase
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
     * @expectedException InvalidArgumentException
     */
    public function testGetLevelNameThrows()
    {
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
     * @expectedException LogicException
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
     * @expectedException LogicException
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
            ->setMethods(['__invoke'])
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

    public function tearDown()
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
            'without microseconds' => [false, PHP_VERSION_ID >= 70100 ? 'assertNotSame' : 'assertSame', 'Y-m-d\TH:i:sP'],
        ];
    }
}
