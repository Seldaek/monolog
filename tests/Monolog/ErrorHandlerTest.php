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

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Psr\Log\LogLevel;

class ErrorHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testRegister()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);

        $this->assertInstanceOf(ErrorHandler::class, ErrorHandler::register($logger, false, false, false));
    }

    #[WithoutErrorHandler]
    public function testHandleError()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $phpunitHandler = set_error_handler($prevHandler = function () {
        });

        try {
            $errHandler->registerErrorHandler([], true);
            $prop = $this->getPrivatePropertyValue($errHandler, 'previousErrorHandler');
            $this->assertTrue(\is_callable($prop));
            $this->assertSame($prevHandler, $prop);

            $resHandler = $errHandler->registerErrorHandler([E_USER_NOTICE => LogLevel::EMERGENCY], false);
            $this->assertSame($errHandler, $resHandler);
            trigger_error('Foo', E_USER_ERROR);
            $this->assertCount(1, $handler->getRecords());
            $this->assertTrue($handler->hasErrorRecords());
            // stack traces are not captured unless captureStackTraces() is called
            $this->assertArrayNotHasKey('exception', $handler->getRecords()[0]->context);
            trigger_error('Foo', E_USER_NOTICE);
            $this->assertCount(2, $handler->getRecords());
            // check that the remapping of notice to emergency above worked
            $this->assertTrue($handler->hasEmergencyRecords());
            $this->assertFalse($handler->hasNoticeRecords());
        } finally {
            // restore previous handler
            set_error_handler($phpunitHandler);
        }
    }

    #[WithoutErrorHandler]
    public function testCaptureStackTraces()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $phpunitHandler = set_error_handler(function () {
        });

        try {
            $errHandler->registerErrorHandler([], false);
            $this->assertSame($errHandler, $errHandler->captureStackTraces());

            self::triggerNestedError();

            $context = $handler->getRecords()[0]->context;
            $this->assertInstanceOf(\ErrorException::class, $e = $context['exception']);
            $this->assertSame('Foo', $e->getMessage());
            $this->assertSame(E_USER_WARNING, $e->getSeverity());
            $this->assertSame(__FILE__, $e->getFile());

            $trace = $e->getTrace();
            // the trace must start at the call which raised the error, not in the ErrorHandler
            $this->assertSame('trigger_error', $trace[0]['function']);
            $this->assertSame('triggerNestedError', $trace[1]['function']);
            $this->assertSame(self::class, $trace[1]['class']);
            foreach ($trace as $frame) {
                $this->assertNotSame(ErrorHandler::class, $frame['class'] ?? null);
                // PHP keeps the file name on include/require frames no matter what
                if (\in_array($frame['function'], ['include', 'include_once', 'require', 'require_once'], true)) {
                    continue;
                }
                // arguments are excluded to avoid leaking sensitive data and holding on to objects
                $this->assertArrayNotHasKey('args', $frame);
            }
        } finally {
            set_error_handler($phpunitHandler);
        }
    }

    #[WithoutErrorHandler]
    public function testCaptureStackTracesAreRenderedByFormatters()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $phpunitHandler = set_error_handler(function () {
        });

        try {
            $errHandler->registerErrorHandler([], false);
            $errHandler->captureStackTraces();

            self::triggerNestedError();

            $output = (new LineFormatter())->includeStacktraces(true)->format($handler->getRecords()[0]);
            $this->assertStringContainsString('[stacktrace]', $output);
            $this->assertStringContainsString('triggerNestedError()', $output);
            $this->assertStringNotContainsString('handleError', $output);
        } finally {
            set_error_handler($phpunitHandler);
        }
    }

    #[WithoutErrorHandler]
    public function testCaptureStackTracesOnlyForGivenErrorTypes()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $phpunitHandler = set_error_handler(function () {
        });

        try {
            $errHandler->registerErrorHandler([], false);
            $errHandler->captureStackTraces(true, E_USER_WARNING);

            trigger_error('Foo', E_USER_WARNING);
            trigger_error('Bar', E_USER_NOTICE);

            $records = $handler->getRecords();
            $this->assertArrayHasKey('exception', $records[0]->context);
            $this->assertArrayNotHasKey('exception', $records[1]->context);

            $errHandler->captureStackTraces(false);
            trigger_error('Baz', E_USER_WARNING);
            $this->assertArrayNotHasKey('exception', $handler->getRecords()[2]->context);
        } finally {
            set_error_handler($phpunitHandler);
        }
    }

    #[WithoutErrorHandler]
    public function testFatalHandlerAttachesStackTrace()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $phpunitHandler = set_error_handler(function () {
        });

        try {
            $errHandler->registerErrorHandler([], false);
            $errHandler->registerFatalHandler();

            // fatal errors are only reported by the fatal handler, to avoid duplicate log entries
            self::triggerNestedError(E_USER_ERROR);
            $this->assertCount(0, $handler->getRecords());

            $errHandler->handleFatalError();

            $context = $handler->getRecords()[0]->context;
            $this->assertInstanceOf(\ErrorException::class, $e = $context['exception']);
            $this->assertSame(E_USER_ERROR, $e->getSeverity());
            $this->assertSame('Foo', $e->getMessage());
            // the raw trace is kept in the context as it always was
            $this->assertSame($context['trace'], $e->getTrace());
            $this->assertSame('trigger_error', $e->getTrace()[0]['function']);
        } finally {
            set_error_handler($phpunitHandler);
        }
    }

    /**
     * PHP 8.5+ reports a trace for fatal errors which never reach the error handler, see the
     * fatal_error_backtraces ini setting
     */
    #[RequiresPhp('>= 8.5')]
    public function testFatalHandlerLogsNativeBacktrace()
    {
        $code = <<<'PHP'
            <?php
            require '%s';

            $handler = new Monolog\Handler\StreamHandler('php://stdout');
            $handler->setFormatter((new Monolog\Formatter\LineFormatter())->includeStacktraces(true));
            Monolog\ErrorHandler::register(new Monolog\Logger('test', [$handler]), false, false);

            function eat(int $depth): void
            {
                if ($depth > 0) {
                    eat($depth - 1);

                    return;
                }

                $data = [];
                while (true) {
                    $data[] = str_repeat('x', 200000);
                }
            }

            eat(3);
            PHP;

        $script = sys_get_temp_dir().'/monolog-fatal-backtrace-'.getmypid().'.php';
        file_put_contents($script, sprintf($code, __DIR__.'/../../vendor/autoload.php'));

        try {
            $output = (string) shell_exec(escapeshellarg(PHP_BINARY).' -d memory_limit=16M -d display_errors=0 -d log_errors=0 -d fatal_error_backtraces=1 '.escapeshellarg($script).' 2>&1');
        } finally {
            unlink($script);
        }

        $this->assertStringContainsString('Fatal Error (E_ERROR): Allowed memory size', $output);
        $this->assertStringContainsString('[stacktrace]', $output);
        $this->assertStringContainsString('eat()', $output);
    }

    private static function triggerNestedError(int $code = E_USER_WARNING): void
    {
        trigger_error('Foo', $code);
    }

    public static function fatalHandlerProvider()
    {
        return [
            [null, 10, str_repeat(' ', 1024 * 10), LogLevel::ALERT],
            [LogLevel::DEBUG, 15, str_repeat(' ', 1024 * 15), LogLevel::DEBUG],
        ];
    }

    protected function getPrivatePropertyValue($instance, $property)
    {
        $ref = new \ReflectionClass(\get_class($instance));
        $prop = $ref->getProperty($property);

        return $prop->getValue($instance);
    }

    #[DataProvider('fatalHandlerProvider')]
    #[WithoutErrorHandler]
    public function testFatalHandler(
        $level,
        $reservedMemorySize,
        $expectedReservedMemory,
        $expectedFatalLevel
    ) {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);
        $res = $errHandler->registerFatalHandler($level, $reservedMemorySize);

        $this->assertSame($res, $errHandler);
        $this->assertTrue($this->getPrivatePropertyValue($errHandler, 'hasFatalErrorHandler'));
        $this->assertEquals($expectedReservedMemory, $this->getPrivatePropertyValue($errHandler, 'reservedMemory'));
        $this->assertEquals($expectedFatalLevel, $this->getPrivatePropertyValue($errHandler, 'fatalLevel'));
    }

    #[WithoutErrorHandler]
    public function testHandleException()
    {
        $logger = new Logger('test', [$handler = new TestHandler]);
        $errHandler = new ErrorHandler($logger);

        $resHandler = $errHandler->registerExceptionHandler($map = ['Monolog\CustomTestException' => LogLevel::DEBUG, 'TypeError' => LogLevel::NOTICE, 'Throwable' => LogLevel::WARNING], false);
        $this->assertSame($errHandler, $resHandler);

        $map['ParseError'] = LogLevel::CRITICAL;
        $prop = $this->getPrivatePropertyValue($errHandler, 'uncaughtExceptionLevelMap');
        $this->assertSame($map, $prop);

        $errHandler->registerExceptionHandler([], true);
        $prop = $this->getPrivatePropertyValue($errHandler, 'previousExceptionHandler');
        $this->assertTrue(\is_callable($prop));

        restore_exception_handler();
        restore_exception_handler();
    }

    public function testCodeToString()
    {
        $method = new \ReflectionMethod(ErrorHandler::class, 'codeToString');

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
        $this->assertEquals('E_STRICT', $method->invokeArgs(null, [2048]));
        $this->assertEquals('E_RECOVERABLE_ERROR', $method->invokeArgs(null, [E_RECOVERABLE_ERROR]));
        $this->assertEquals('E_DEPRECATED', $method->invokeArgs(null, [E_DEPRECATED]));
        $this->assertEquals('E_USER_DEPRECATED', $method->invokeArgs(null, [E_USER_DEPRECATED]));

        $this->assertEquals('Unknown PHP error', $method->invokeArgs(null, [E_ALL]));
    }
}

class CustomTestException extends \Exception
{
}
class CustomCustomException extends CustomTestException
{
}
