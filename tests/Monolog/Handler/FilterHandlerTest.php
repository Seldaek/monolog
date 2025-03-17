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

class FilterHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Handler\FilterHandler::isHandling
     */
    public function testIsHandling()
    {
        $test    = new TestHandler();
        $handler = new FilterHandler($test, Level::Info, Level::Notice);
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Debug)));
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Info)));
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Notice)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Warning)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Error)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Critical)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Alert)));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Emergency)));
    }

    /**
     * @covers Monolog\Handler\FilterHandler::handle
     * @covers Monolog\Handler\FilterHandler::setAcceptedLevels
     * @covers Monolog\Handler\FilterHandler::isHandling
     */
    public function testHandleProcessOnlyNeededLevels()
    {
        $test    = new TestHandler();
        $handler = new FilterHandler($test, Level::Info, Level::Notice);

        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($test->hasDebugRecords());

        $handler->handle($this->getRecord(Level::Info));
        $this->assertTrue($test->hasInfoRecords());
        $handler->handle($this->getRecord(Level::Notice));
        $this->assertTrue($test->hasNoticeRecords());

        $handler->handle($this->getRecord(Level::Warning));
        $this->assertFalse($test->hasWarningRecords());
        $handler->handle($this->getRecord(Level::Error));
        $this->assertFalse($test->hasErrorRecords());
        $handler->handle($this->getRecord(Level::Critical));
        $this->assertFalse($test->hasCriticalRecords());
        $handler->handle($this->getRecord(Level::Alert));
        $this->assertFalse($test->hasAlertRecords());
        $handler->handle($this->getRecord(Level::Emergency));
        $this->assertFalse($test->hasEmergencyRecords());

        $test    = new TestHandler();
        $handler = new FilterHandler($test, [Level::Info, Level::Error]);

        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($test->hasDebugRecords());
        $handler->handle($this->getRecord(Level::Info));
        $this->assertTrue($test->hasInfoRecords());
        $handler->handle($this->getRecord(Level::Notice));
        $this->assertFalse($test->hasNoticeRecords());
        $handler->handle($this->getRecord(Level::Error));
        $this->assertTrue($test->hasErrorRecords());
        $handler->handle($this->getRecord(Level::Critical));
        $this->assertFalse($test->hasCriticalRecords());
    }

    /**
     * @covers Monolog\Handler\FilterHandler::setAcceptedLevels
     * @covers Monolog\Handler\FilterHandler::getAcceptedLevels
     */
    public function testAcceptedLevelApi()
    {
        $test    = new TestHandler();
        $handler = new FilterHandler($test);

        $levels = [Level::Info, Level::Error];
        $levelsExpect = [Level::Info, Level::Error];
        $handler->setAcceptedLevels($levels);
        $this->assertSame($levelsExpect, $handler->getAcceptedLevels());

        $handler->setAcceptedLevels(['info', 'error']);
        $this->assertSame($levelsExpect, $handler->getAcceptedLevels());

        $levels = [Level::Critical, Level::Alert, Level::Emergency];
        $handler->setAcceptedLevels(Level::Critical, Level::Emergency);
        $this->assertSame($levels, $handler->getAcceptedLevels());

        $handler->setAcceptedLevels('critical', 'emergency');
        $this->assertSame($levels, $handler->getAcceptedLevels());
    }

    /**
     * @covers Monolog\Handler\FilterHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test    = new TestHandler();
        $handler = new FilterHandler($test, Level::Debug, Level::Emergency);
        $handler->pushProcessor(
            function ($record) {
                $record->extra['foo'] = true;

                return $record;
            }
        );
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }

    /**
     * @covers Monolog\Handler\FilterHandler::handle
     */
    public function testHandleRespectsBubble()
    {
        $test = new TestHandler();

        $handler = new FilterHandler($test, Level::Info, Level::Notice, false);
        $this->assertTrue($handler->handle($this->getRecord(Level::Info)));
        $this->assertFalse($handler->handle($this->getRecord(Level::Warning)));

        $handler = new FilterHandler($test, Level::Info, Level::Notice, true);
        $this->assertFalse($handler->handle($this->getRecord(Level::Info)));
        $this->assertFalse($handler->handle($this->getRecord(Level::Warning)));
    }

    /**
     * @covers Monolog\Handler\FilterHandler::handle
     */
    public function testHandleWithCallback()
    {
        $test    = new TestHandler();
        $handler = new FilterHandler(
            function ($record, $handler) use ($test) {
                return $test;
            },
            Level::Info,
            Level::Notice,
            false
        );
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertTrue($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\FilterHandler::handle
     */
    public function testHandleWithBadCallbackThrowsException()
    {
        $handler = new FilterHandler(
            function ($record, $handler) {
                return 'foo';
            }
        );

        $this->expectException(\RuntimeException::class);

        $handler->handle($this->getRecord(Level::Warning));
    }

    public function testHandleEmptyBatch()
    {
        $test = new TestHandler();
        $handler = new FilterHandler($test);
        $handler->handleBatch([]);
        $this->assertSame([], $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\FilterHandler::handle
     * @covers Monolog\Handler\FilterHandler::reset
     */
    public function testResetTestHandler()
    {
        $test = new TestHandler();
        $handler = new FilterHandler($test, [Level::Info, Level::Error]);

        $handler->handle($this->getRecord(Level::Info));
        $this->assertTrue($test->hasInfoRecords());

        $handler->handle($this->getRecord(Level::Error));
        $this->assertTrue($test->hasErrorRecords());

        $handler->reset();

        $this->assertFalse($test->hasInfoRecords());
        $this->assertFalse($test->hasInfoRecords());

        $this->assertSame([], $test->getRecords());
    }
}
