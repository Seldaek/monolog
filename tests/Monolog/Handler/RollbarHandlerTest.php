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
use Monolog\Test\TestCase;
use Monolog\Level;
use PHPUnit\Framework\MockObject\MockObject;
use Rollbar\RollbarLogger;

/**
 * @author Erik Johansson <erik.pm.johansson@gmail.com>
 * @see    https://rollbar.com/docs/notifier/rollbar-php/
 *
 * @coversDefaultClass Monolog\Handler\RollbarHandler
 *
 * @requires function \Rollbar\RollbarLogger::__construct
 */
class RollbarHandlerTest extends TestCase
{
    private RollbarLogger&MockObject $rollbarLogger;

    private array $reportedExceptionArguments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupRollbarLoggerMock();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->rollbarLogger, $this->reportedExceptionArguments);
    }

    /**
     * When reporting exceptions to Rollbar the
     * level has to be set in the payload data
     */
    public function testExceptionLogLevel()
    {
        $handler = $this->createHandler();

        $handler->handle($this->createExceptionRecord(Level::Debug));

        $this->assertEquals('debug', $this->reportedExceptionArguments['payload']['level']);
    }

    private function setupRollbarLoggerMock()
    {
        $config = [
            'access_token' => 'ad865e76e7fb496fab096ac07b1dbabb',
            'environment' => 'test',
        ];

        $this->rollbarLogger = $this->getMockBuilder(RollbarLogger::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['log'])
            ->getMock();

        $this->rollbarLogger
            ->expects($this->any())
            ->method('log')
            ->willReturnCallback(function ($exception, $context, $payload) {
                $this->reportedExceptionArguments = compact('exception', 'context', 'payload');
            });
    }

    private function createHandler(): RollbarHandler
    {
        return new RollbarHandler($this->rollbarLogger, Level::Debug);
    }

    private function createExceptionRecord($level = Level::Debug, $message = 'test', $exception = null): array
    {
        return $this->getRecord($level, $message, [
            'exception' => $exception ?: new Exception(),
        ]);
    }
}
