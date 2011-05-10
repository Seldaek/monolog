<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\TestCase;
use Monolog\Logger;

class GroupHandlerTest extends TestCase
{
    public function testHandle()
    {
        $testHandlers = array(new TestHandler(), new TestHandler());
        $handler = new GroupHandler($testHandlers);
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertTrue(count($test->getRecords()) === 2);
        }
    }

    public function testHandleBatch()
    {
        $testHandlers = array(new TestHandler(), new TestHandler());
        $handler = new GroupHandler($testHandlers);
        $handler->handleBatch(array($this->getRecord(Logger::DEBUG), $this->getRecord(Logger::INFO)));
        foreach ($testHandlers as $test) {
            $this->assertTrue($test->hasDebugRecords());
            $this->assertTrue($test->hasInfoRecords());
            $this->assertTrue(count($test->getRecords()) === 2);
        }
    }
}
