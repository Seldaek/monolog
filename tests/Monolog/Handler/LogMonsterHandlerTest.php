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

class LogMonsterHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * @covers Monolog\Handler\LogMonsterHandler::handle
     * @covers Monolog\Handler\LogMonsterHandler::close
     */
    public function testWellFedMonsterStaysQuiet()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 2);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->close();

        $this->assertCount(0, $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::handle
     * @covers Monolog\Handler\LogMonsterHandler::close
     */
    public function testHungryMonsterGetsAngryOnClose()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 3);
        $handler->handle($this->getRecord(Level::Info));
        $handler->close();

        $records = $test->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Error, $records[0]->level);
        $this->assertSame(1, $records[0]->context['eaten']);
        $this->assertSame(3, $records[0]->context['hunger']);
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::__construct
     * @covers Monolog\Handler\LogMonsterHandler::close
     */
    public function testAngerLevelIsConfigurable()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 1, Level::Critical);
        $handler->close();

        $this->assertTrue($test->hasCriticalRecords());
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::feed
     * @covers Monolog\Handler\LogMonsterHandler::close
     */
    public function testManualFeedSilencesTheMonster()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 5);
        $handler->feed();
        $handler->close();

        $this->assertCount(0, $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::handle
     */
    public function testWantsContextChipsIgnoresPlainRecords()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 1, Level::Error, 'log-monster', wantsContextChips: true);
        $handler->handle($this->getRecord(Level::Info));
        $handler->close();

        // a record without context does not count, so the monster goes hungry
        $this->assertTrue($test->hasErrorRecords());
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::handle
     */
    public function testWantsContextChipsIsSatisfiedByRecordsWithContext()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 1, Level::Error, 'log-monster', wantsContextChips: true);
        $handler->handle($this->getRecord(Level::Info, 'test', ['chocolate' => 'chips']));
        $handler->close();

        $this->assertCount(0, $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::reset
     */
    public function testResetReArmsTheMonster()
    {
        $test = new TestHandler();
        $test->setSkipReset(true);
        $handler = new LogMonsterHandler($test, 1);
        $handler->handle($this->getRecord(Level::Info));
        $handler->reset();
        $handler->close();

        // after reset the monster is hungry again and never got fed
        $this->assertTrue($test->hasErrorRecords());
    }

    /**
     * @covers Monolog\Handler\LogMonsterHandler::close
     */
    public function testComplaintUsesConfiguredChannel()
    {
        $test = new TestHandler();
        $handler = new LogMonsterHandler($test, 1, Level::Error, 'cookie-jar');
        $handler->close();

        $records = $test->getRecords();
        $this->assertSame('cookie-jar', $records[0]->channel);
    }

    public function testIsHandlingAlwaysReturnsTrue()
    {
        $handler = new LogMonsterHandler(new TestHandler(), 1);

        $this->assertTrue($handler->isHandling($this->getRecord(Level::Debug)));
    }
}
