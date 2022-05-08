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

use Exception;
use Monolog\ErrorHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Test\TestCase;
use PhpConsole\Connector;
use PhpConsole\Dispatcher\Debug as DebugDispatcher;
use PhpConsole\Dispatcher\Errors as ErrorDispatcher;
use PhpConsole\Handler as VendorPhpConsoleHandler;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers Monolog\Handler\PHPConsoleHandler
 * @author Sergey Barbushin https://www.linkedin.com/in/barbushin
 */
class PHPConsoleHandlerTest extends TestCase
{
    protected Connector&MockObject $connector;
    protected DebugDispatcher&MockObject $debugDispatcher;
    protected ErrorDispatcher&MockObject $errorDispatcher;

    protected function setUp(): void
    {
        // suppress warnings until https://github.com/barbushin/php-console/pull/173 is merged
        $previous = error_reporting(0);
        if (!class_exists('PhpConsole\Connector')) {
            error_reporting($previous);
            $this->markTestSkipped('PHP Console library not found. See https://github.com/barbushin/php-console#installation');
        }
        if (!class_exists('PhpConsole\Handler')) {
            error_reporting($previous);
            $this->markTestSkipped('PHP Console library not found. See https://github.com/barbushin/php-console#installation');
        }
        error_reporting($previous);
        $this->connector = $this->initConnectorMock();

        $this->debugDispatcher = $this->initDebugDispatcherMock($this->connector);
        $this->connector->setDebugDispatcher($this->debugDispatcher);

        $this->errorDispatcher = $this->initErrorDispatcherMock($this->connector);
        $this->connector->setErrorsDispatcher($this->errorDispatcher);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->connector, $this->debugDispatcher, $this->errorDispatcher);
    }

    protected function initDebugDispatcherMock(Connector $connector)
    {
        return $this->getMockBuilder('PhpConsole\Dispatcher\Debug')
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatchDebug'])
            ->setConstructorArgs([$connector, $connector->getDumper()])
            ->getMock();
    }

    protected function initErrorDispatcherMock(Connector $connector)
    {
        return $this->getMockBuilder('PhpConsole\Dispatcher\Errors')
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatchError', 'dispatchException'])
            ->setConstructorArgs([$connector, $connector->getDumper()])
            ->getMock();
    }

    protected function initConnectorMock()
    {
        $connector = $this->getMockBuilder('PhpConsole\Connector')
            ->disableOriginalConstructor()
            ->onlyMethods([
                'sendMessage',
                'onShutDown',
                'isActiveClient',
                'setSourcesBasePath',
                'setServerEncoding',
                'setPassword',
                'enableSslOnlyMode',
                'setAllowedIpMasks',
                'setHeadersLimit',
                'startEvalRequestsListener',
            ])
            ->getMock();

        $connector->expects($this->any())
            ->method('isActiveClient')
            ->will($this->returnValue(true));

        return $connector;
    }

    protected function getHandlerDefaultOption($name)
    {
        $handler = new PHPConsoleHandler([], $this->connector);
        $options = $handler->getOptions();

        return $options[$name];
    }

    protected function initLogger($handlerOptions = [], $level = Level::Debug)
    {
        return new Logger('test', [
            new PHPConsoleHandler($handlerOptions, $this->connector, $level),
        ]);
    }

    public function testInitWithDefaultConnector()
    {
        $handler = new PHPConsoleHandler();
        $this->assertEquals(spl_object_hash(Connector::getInstance()), spl_object_hash($handler->getConnector()));
    }

    public function testInitWithCustomConnector()
    {
        $handler = new PHPConsoleHandler([], $this->connector);
        $this->assertEquals(spl_object_hash($this->connector), spl_object_hash($handler->getConnector()));
    }

    public function testDebug()
    {
        $this->debugDispatcher->expects($this->once())->method('dispatchDebug')->with($this->equalTo('test'));
        $this->initLogger()->debug('test');
    }

    public function testDebugContextInMessage()
    {
        $message = 'test';
        $tag = 'tag';
        $context = [$tag, 'custom' => mt_rand()];
        $expectedMessage = $message . ' ' . json_encode(array_slice($context, 1));
        $this->debugDispatcher->expects($this->once())->method('dispatchDebug')->with(
            $this->equalTo($expectedMessage),
            $this->equalTo($tag)
        );
        $this->initLogger()->debug($message, $context);
    }

    public function testDebugTags($tagsContextKeys = null)
    {
        $expectedTags = mt_rand();
        $logger = $this->initLogger($tagsContextKeys ? ['debugTagsKeysInContext' => $tagsContextKeys] : []);
        if (!$tagsContextKeys) {
            $tagsContextKeys = $this->getHandlerDefaultOption('debugTagsKeysInContext');
        }
        foreach ($tagsContextKeys as $key) {
            $debugDispatcher = $this->initDebugDispatcherMock($this->connector);
            $debugDispatcher->expects($this->once())->method('dispatchDebug')->with(
                $this->anything(),
                $this->equalTo($expectedTags)
            );
            $this->connector->setDebugDispatcher($debugDispatcher);
            $logger->debug('test', [$key => $expectedTags]);
        }
    }

    public function testError($classesPartialsTraceIgnore = null)
    {
        $code = E_USER_NOTICE;
        $message = 'message';
        $file = __FILE__;
        $line = __LINE__;
        $this->errorDispatcher->expects($this->once())->method('dispatchError')->with(
            $this->equalTo($code),
            $this->equalTo($message),
            $this->equalTo($file),
            $this->equalTo($line),
            $classesPartialsTraceIgnore ?: $this->equalTo($this->getHandlerDefaultOption('classesPartialsTraceIgnore'))
        );
        $errorHandler = ErrorHandler::register($this->initLogger($classesPartialsTraceIgnore ? ['classesPartialsTraceIgnore' => $classesPartialsTraceIgnore] : []), false);
        $errorHandler->registerErrorHandler([], false, E_USER_WARNING);
        $reflMethod = new \ReflectionMethod($errorHandler, 'handleError');
        $reflMethod->invoke($errorHandler, $code, $message, $file, $line);
    }

    public function testException()
    {
        $e = new Exception();
        $this->errorDispatcher->expects($this->once())->method('dispatchException')->with(
            $this->equalTo($e)
        );
        $handler = $this->initLogger();
        $handler->log(
            \Psr\Log\LogLevel::ERROR,
            sprintf('Uncaught Exception %s: "%s" at %s line %s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
            ['exception' => $e]
        );
    }

    public function testWrongOptionsThrowsException()
    {
        $this->expectException(\Exception::class);

        new PHPConsoleHandler(['xxx' => 1]);
    }

    public function testOptionEnabled()
    {
        $this->debugDispatcher->expects($this->never())->method('dispatchDebug');
        $this->initLogger(['enabled' => false])->debug('test');
    }

    public function testOptionClassesPartialsTraceIgnore()
    {
        $this->testError(['Class', 'Namespace\\']);
    }

    public function testOptionDebugTagsKeysInContext()
    {
        $this->testDebugTags(['key1', 'key2']);
    }

    public function testOptionUseOwnErrorsAndExceptionsHandler()
    {
        $this->initLogger(['useOwnErrorsHandler' => true, 'useOwnExceptionsHandler' => true]);
        $this->assertEquals([VendorPhpConsoleHandler::getInstance(), 'handleError'], set_error_handler(function () {
        }));
        $this->assertEquals([VendorPhpConsoleHandler::getInstance(), 'handleException'], set_exception_handler(function () {
        }));
    }

    public static function provideConnectorMethodsOptionsSets()
    {
        return [
            ['sourcesBasePath', 'setSourcesBasePath', __DIR__],
            ['serverEncoding', 'setServerEncoding', 'cp1251'],
            ['password', 'setPassword', '******'],
            ['enableSslOnlyMode', 'enableSslOnlyMode', true, false],
            ['ipMasks', 'setAllowedIpMasks', ['127.0.0.*']],
            ['headersLimit', 'setHeadersLimit', 2500],
            ['enableEvalListener', 'startEvalRequestsListener', true, false],
        ];
    }

    /**
     * @dataProvider provideConnectorMethodsOptionsSets
     */
    public function testOptionCallsConnectorMethod($option, $method, $value, $isArgument = true)
    {
        $expectCall = $this->connector->expects($this->once())->method($method);
        if ($isArgument) {
            $expectCall->with($value);
        }
        new PHPConsoleHandler([$option => $value], $this->connector);
    }

    public function testOptionDetectDumpTraceAndSource()
    {
        new PHPConsoleHandler(['detectDumpTraceAndSource' => true], $this->connector);
        $this->assertTrue($this->connector->getDebugDispatcher()->detectTraceAndSource);
    }

    public static function provideDumperOptionsValues()
    {
        return [
            ['dumperLevelLimit', 'levelLimit', 1001],
            ['dumperItemsCountLimit', 'itemsCountLimit', 1002],
            ['dumperItemSizeLimit', 'itemSizeLimit', 1003],
            ['dumperDumpSizeLimit', 'dumpSizeLimit', 1004],
            ['dumperDetectCallbacks', 'detectCallbacks', true],
        ];
    }

    /**
     * @dataProvider provideDumperOptionsValues
     */
    public function testDumperOptions($option, $dumperProperty, $value)
    {
        new PHPConsoleHandler([$option => $value], $this->connector);
        $this->assertEquals($value, $this->connector->getDumper()->$dumperProperty);
    }
}
