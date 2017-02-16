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

use Monolog\Handler\TestHandler;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleError()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $errHandler->registerErrorHandler([E_USER_NOTICE => Logger::EMERGENCY], false);
        trigger_error('Foo', E_USER_ERROR);
        $this->assertCount(1, $handler->getRecords());
        $this->assertTrue($handler->hasErrorRecords());
        trigger_error('Foo', E_USER_NOTICE);
        $this->assertCount(2, $handler->getRecords());
        $this->assertTrue($handler->hasEmergencyRecords());
    }

    public function testHandleException()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $errHandler->registerExceptionHandler(['Monolog\CustomTestException' => Logger::ALERT, 'Throwable' => Logger::WARNING], false);

        try {
            throw new CustomCustomException();
            $this->assertCount(1, $handler->getRecords());
            $this->assertTrue($handler->hasAlertRecords());
        } catch (\Throwable $e) {
        }
        try {
            throw new CustomTestException();
            $this->assertCount(2, $handler->getRecords());
            $this->assertTrue($handler->hasAlertRecords());
        } catch (\Throwable $e) {
        }
        try {
            throw new RuntimeException();
            $this->assertCount(3, $handler->getRecords());
            $this->assertTrue($handler->hasWarningRecords());
        } catch (\Throwable $e) {
        }
    }

    public function testCodeToString()
    {
        $method = new \ReflectionMethod(ErrorHandler::class, 'codeToString');
        $method->setAccessible(true);

        $this->assertEquals('E_ERROR', $method->invokeArgs(null, [E_ERROR]));
        $this->assertEquals('E_WARNING', $method->invokeArgs(null, [E_WARNING]));
        $this->assertEquals('E_PARSE', $method->invokeArgs(null, [E_PARSE]));
        $this->assertEquals('E_NOTICE', $method->invokeArgs(null, [E_NOTICE]));
        $this->assertEquals('E_CORE_ERROR', $method->invokeArgs(null, [E_CORE_ERROR]));
        $this->assertEquals('E_CORE_WARNING', $method->invokeArgs(null, [E_CORE_WARNING]));
        $this->assertEquals('E_COMPILE_ERROR', $method->invokeArgs(null, [E_COMPILE_ERROR]));
        $this->assertEquals('E_COMPILE_WARNING', $method->invokeArgs(null, [E_COMPILE_WARNING]));
        $this->assertEquals('E_USER_ERROR', $method->invokeArgs(null, [E_USER_ERROR]));
        $this->assertEquals('E_USER_WARNING', $method->invokeArgs(null, [E_USER_WARNING]));
        $this->assertEquals('E_USER_NOTICE', $method->invokeArgs(null, [E_USER_NOTICE]));
        $this->assertEquals('E_STRICT', $method->invokeArgs(null, [E_STRICT]));
        $this->assertEquals('E_RECOVERABLE_ERROR', $method->invokeArgs(null, [E_RECOVERABLE_ERROR]));
        $this->assertEquals('E_DEPRECATED', $method->invokeArgs(null, [E_DEPRECATED]));
        $this->assertEquals('E_USER_DEPRECATED', $method->invokeArgs(null, [E_USER_DEPRECATED]));

        $this->assertEquals('Unknown PHP error', $method->invokeArgs(null, ['RANDOM_TEXT']));
        $this->assertEquals('Unknown PHP error', $method->invokeArgs(null, [E_ALL]));
    }
}

class CustomTestException extends \Exception
{
}
class CustomCustomException extends CustomTestException
{
}
