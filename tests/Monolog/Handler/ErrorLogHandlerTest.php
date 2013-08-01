<?php

namespace Monolog\Handler;

use Monolog\TestCase;
use Monolog\Logger;

function error_log()
{
    $GLOBALS['error_log'] = func_get_args();
}

class ErrorLogHandlerTest extends TestCase
{

    protected function setUp()
    {
        $GLOBALS['error_log'] = array();
    }

    /**
     * @covers Monolog\Handler\ErrorLogHandler::__construct
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The given message type "42" is not supported
     */
    public function testShouldNotAcceptAnInvalidTypeOnContructor()
    {
        new ErrorLogHandler(42);
    }

    /**
     * @covers Monolog\Handler\ErrorLogHandler::write
     */
    public function testShouldLogMessagesUsingErrorLogFuncion()
    {
        $type = ErrorLogHandler::OPERATING_SYSTEM;
        $handler = new ErrorLogHandler($type);
        $handler->handle($this->getRecord(Logger::ERROR));

        $this->assertStringMatchesFormat('[%s] test.ERROR: test [] []', $GLOBALS['error_log'][0]);
        $this->assertSame($GLOBALS['error_log'][1], $type);
    }
}
