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

use Monolog\Level;
use Monolog\Test\TestCase;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossed\ChannelLevelActivationStrategy;
use Psr\Log\LogLevel;

class FingersCrossedHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\FingersCrossedHandler::__construct
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleBuffers()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->handle($this->getRecord(Level::Warning));
        $handler->close();
        $this->assertTrue($test->hasInfoRecords());
        $this->assertCount(3, $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleStopsBufferingAfterTrigger()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getRecord(Level::Warning));
        $handler->handle($this->getRecord(Level::Debug));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     * @covers Monolog\Handler\FingersCrossedHandler::reset
     */
    public function testHandleResetBufferingAfterReset()
    {
        $test = new TestHandler();
        $test->setSkipReset(true);
        $handler = new FingersCrossedHandler($test);
        $handler->handle($this->getRecord(Level::Warning));
        $handler->handle($this->getRecord(Level::Debug));
        $handler->reset();
        $handler->handle($this->getRecord(Level::Info));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleResetBufferingAfterBeingTriggeredWhenStopBufferingIsDisabled()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Level::Warning, 0, false, false);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Warning));
        $handler->handle($this->getRecord(Level::Info));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleBufferLimit()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Level::Warning, 2);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleWithCallback()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler(function ($record, $handler) use ($test) {
            return $test;
        });
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasInfoRecords());
        $this->assertCount(3, $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleWithBadCallbackThrowsException()
    {
        $handler = new FingersCrossedHandler(function ($record, $handler) {
            return 'foo';
        });

        $this->expectException(\RuntimeException::class);

        $handler->handle($this->getRecord(Level::Warning));
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::isHandling
     */
    public function testIsHandlingAlways()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Level::Error);
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Debug)));
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::__construct
     * @covers Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::__construct
     * @covers Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::isHandlerActivated
     */
    public function testErrorLevelActivationStrategy()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ErrorLevelActivationStrategy(Level::Warning));
        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($test->hasDebugRecords());
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasDebugRecords());
        $this->assertTrue($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::__construct
     * @covers Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::__construct
     * @covers Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::isHandlerActivated
     */
    public function testErrorLevelActivationStrategyWithPsrLevel()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ErrorLevelActivationStrategy('warning'));
        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($test->hasDebugRecords());
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasDebugRecords());
        $this->assertTrue($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::__construct
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testOverrideActivationStrategy()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ErrorLevelActivationStrategy('warning'));
        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($test->hasDebugRecords());
        $handler->activate();
        $this->assertTrue($test->hasDebugRecords());
        $handler->handle($this->getRecord(Level::Info));
        $this->assertTrue($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossed\ChannelLevelActivationStrategy::__construct
     * @covers Monolog\Handler\FingersCrossed\ChannelLevelActivationStrategy::isHandlerActivated
     */
    public function testChannelLevelActivationStrategy()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ChannelLevelActivationStrategy(Level::Error, ['othertest' => Level::Debug]));
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertFalse($test->hasWarningRecords());
        $record = $this->getRecord(Level::Debug, channel: 'othertest');
        $handler->handle($record);
        $this->assertTrue($test->hasDebugRecords());
        $this->assertTrue($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossed\ChannelLevelActivationStrategy::__construct
     * @covers Monolog\Handler\FingersCrossed\ChannelLevelActivationStrategy::isHandlerActivated
     */
    public function testChannelLevelActivationStrategyWithPsrLevels()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ChannelLevelActivationStrategy('error', ['othertest' => 'debug']));
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertFalse($test->hasWarningRecords());
        $record = $this->getRecord(Level::Debug, channel: 'othertest');
        $handler->handle($record);
        $this->assertTrue($test->hasDebugRecords());
        $this->assertTrue($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::handle
     * @covers Monolog\Handler\FingersCrossedHandler::activate
     */
    public function testHandleUsesProcessors()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, Level::Info);
        $handler->pushProcessor(function ($record) {
            $record->extra['foo'] = true;

            return $record;
        });
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::close
     */
    public function testPassthruOnClose()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ErrorLevelActivationStrategy(Level::Warning), 0, true, true, Level::Info);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->handle($this->getRecord(Level::Notice));
        $handler->close();
        $this->assertFalse($test->hasDebugRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue($test->hasNoticeRecords());
    }

    /**
     * @covers Monolog\Handler\FingersCrossedHandler::close
     */
    public function testPsrLevelPassthruOnClose()
    {
        $test = new TestHandler();
        $handler = new FingersCrossedHandler($test, new ErrorLevelActivationStrategy(Level::Warning), 0, true, true, LogLevel::INFO);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->handle($this->getRecord(Level::Notice));
        $handler->close();
        $this->assertFalse($test->hasDebugRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue($test->hasNoticeRecords());
    }
}
