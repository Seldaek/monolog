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

/**
 * @author Kris Buist <krisbuist@gmail.com>
 * @covers \Monolog\Handler\OverflowHandler
 */
class OverflowHandlerTest extends \Monolog\Test\MonologTestCase
{
    public function testNotPassingRecordsBeneathLogLevel()
    {
        $testHandler = new TestHandler();
        $handler = new OverflowHandler($testHandler, [], Level::Info);
        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($testHandler->hasDebugRecords());
    }

    public function testPassThroughWithoutThreshold()
    {
        $testHandler = new TestHandler();
        $handler = new OverflowHandler($testHandler, [], Level::Info);

        $handler->handle($this->getRecord(Level::Info, 'Info 1'));
        $handler->handle($this->getRecord(Level::Info, 'Info 2'));
        $handler->handle($this->getRecord(Level::Warning, 'Warning 1'));

        $this->assertTrue($testHandler->hasInfoThatContains('Info 1'));
        $this->assertTrue($testHandler->hasInfoThatContains('Info 2'));
        $this->assertTrue($testHandler->hasWarningThatContains('Warning 1'));
    }

    /**
     * @test
     */
    public function testHoldingMessagesBeneathThreshold()
    {
        $testHandler = new TestHandler();
        $handler = new OverflowHandler($testHandler, [Level::Info->value => 3]);

        $handler->handle($this->getRecord(Level::Debug, 'debug 1'));
        $handler->handle($this->getRecord(Level::Debug, 'debug 2'));

        foreach (range(1, 3) as $i) {
            $handler->handle($this->getRecord(Level::Info, 'info ' . $i));
        }

        $this->assertTrue($testHandler->hasDebugThatContains('debug 1'));
        $this->assertTrue($testHandler->hasDebugThatContains('debug 2'));
        $this->assertFalse($testHandler->hasInfoRecords());

        $handler->handle($this->getRecord(Level::Info, 'info 4'));

        foreach (range(1, 4) as $i) {
            $this->assertTrue($testHandler->hasInfoThatContains('info ' . $i));
        }
    }

    /**
     * @test
     */
    public function testCombinedThresholds()
    {
        $testHandler = new TestHandler();
        $handler = new OverflowHandler($testHandler, [Level::Info->value => 5, Level::Warning->value => 10]);

        $handler->handle($this->getRecord(Level::Debug));

        foreach (range(1, 5) as $i) {
            $handler->handle($this->getRecord(Level::Info, 'info ' . $i));
        }

        foreach (range(1, 10) as $i) {
            $handler->handle($this->getRecord(Level::Warning, 'warning ' . $i));
        }

        // Only 1 DEBUG records
        $this->assertCount(1, $testHandler->getRecords());

        $handler->handle($this->getRecord(Level::Info, 'info final'));

        // 1 DEBUG + 5 buffered INFO + 1 new INFO
        $this->assertCount(7, $testHandler->getRecords());

        $handler->handle($this->getRecord(Level::Warning, 'warning final'));

        // 1 DEBUG + 6 INFO + 10 buffered WARNING + 1 new WARNING
        $this->assertCount(18, $testHandler->getRecords());

        $handler->handle($this->getRecord(Level::Info, 'Another info'));
        $handler->handle($this->getRecord(Level::Warning, 'Anther warning'));

        // 1 DEBUG + 6 INFO + 11 WARNING + 1 new INFO + 1 new WARNING
        $this->assertCount(20, $testHandler->getRecords());
    }
}
