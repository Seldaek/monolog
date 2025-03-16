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

class BufferHandlerTest extends \Monolog\Test\MonologTestCase
{
    private TestHandler $shutdownCheckHandler;

    /**
     * @covers Monolog\Handler\BufferHandler::__construct
     * @covers Monolog\Handler\BufferHandler::handle
     * @covers Monolog\Handler\BufferHandler::close
     */
    public function testHandleBuffers()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertFalse($test->hasInfoRecords());
        $handler->close();
        $this->assertTrue($test->hasInfoRecords());
        $this->assertCount(2, $test->getRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::close
     * @covers Monolog\Handler\BufferHandler::flush
     */
    public function testPropagatesRecordsAtEndOfRequest()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->handle($this->getRecord(Level::Warning));
        $handler->handle($this->getRecord(Level::Debug));
        $this->shutdownCheckHandler = $test;
        register_shutdown_function([$this, 'checkPropagation']);
    }

    public function checkPropagation()
    {
        if (!$this->shutdownCheckHandler->hasWarningRecords() || !$this->shutdownCheckHandler->hasDebugRecords()) {
            echo '!!! BufferHandlerTest::testPropagatesRecordsAtEndOfRequest failed to verify that the messages have been propagated' . PHP_EOL;
            exit(1);
        }
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleBufferLimit()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 2);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->handle($this->getRecord(Level::Warning));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleBufferLimitWithFlushOnOverflow()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 3, Level::Debug, true, true);

        // send two records
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Debug));
        $this->assertFalse($test->hasDebugRecords());
        $this->assertCount(0, $test->getRecords());

        // overflow
        $handler->handle($this->getRecord(Level::Info));
        $this->assertTrue($test->hasDebugRecords());
        $this->assertCount(3, $test->getRecords());

        // should buffer again
        $handler->handle($this->getRecord(Level::Warning));
        $this->assertCount(3, $test->getRecords());

        $handler->close();
        $this->assertCount(5, $test->getRecords());
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleLevel()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 0, Level::Info);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->handle($this->getRecord(Level::Warning));
        $handler->handle($this->getRecord(Level::Debug));
        $handler->close();
        $this->assertTrue($test->hasWarningRecords());
        $this->assertTrue($test->hasInfoRecords());
        $this->assertFalse($test->hasDebugRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::flush
     */
    public function testFlush()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test, 0);
        $handler->handle($this->getRecord(Level::Debug));
        $handler->handle($this->getRecord(Level::Info));
        $handler->flush();
        $this->assertTrue($test->hasInfoRecords());
        $this->assertTrue($test->hasDebugRecords());
        $this->assertFalse($test->hasWarningRecords());
    }

    /**
     * @covers Monolog\Handler\BufferHandler::handle
     */
    public function testHandleUsesProcessors()
    {
        $test = new TestHandler();
        $handler = new BufferHandler($test);
        $handler->pushProcessor(function ($record) {
            $record->extra['foo'] = true;

            return $record;
        });
        $handler->handle($this->getRecord(Level::Warning));
        $handler->flush();
        $this->assertTrue($test->hasWarningRecords());
        $records = $test->getRecords();
        $this->assertTrue($records[0]['extra']['foo']);
    }

    public function testSetHandler()
    {
        $testOriginal = new TestHandler();
        $handler = new BufferHandler($testOriginal);
        $handler->handle($this->getRecord(Level::Info));

        $testNew = new TestHandler();
        $handler->setHandler($testNew);

        $handler->handle($this->getRecord(Level::Debug));

        $handler->close();

        $this->assertFalse($testOriginal->hasInfoRecords());
        $this->assertFalse($testOriginal->hasDebugRecords());
        $this->assertTrue($testNew->hasInfoRecords());
        $this->assertTrue($testNew->hasDebugRecords());
        $this->assertCount(2, $testNew->getRecords());
    }
}
