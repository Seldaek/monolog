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

use Monolog\Test\TestCase;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

/**
 * @covers Monolog\Handler\PsrHandler::handle
 */
class PsrHandlerTest extends TestCase
{
    public function logLevelProvider()
    {
        $levels = [];
        $monologLogger = new Logger('');

        foreach ($monologLogger->getLevels() as $levelName => $level) {
            $levels[] = [$levelName, $level];
        }

        return $levels;
    }

    /**
     * @dataProvider logLevelProvider
     */
    public function testHandlesAllLevels($levelName, $level)
    {
        $message = 'Hello, world! ' . $level;
        $context = ['foo' => 'bar', 'level' => $level];

        $psrLogger = $this->createMock('Psr\Log\NullLogger');
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(strtolower($levelName), $message, $context);

        $handler = new PsrHandler($psrLogger);
        $handler->handle(['level' => $level, 'level_name' => $levelName, 'message' => $message, 'context' => $context]);
    }

    public function testFormatter()
    {
        $message = 'Hello, world!';
        $context = ['foo' => 'bar'];
        $level = Logger::ERROR;
        $levelName = 'error';

        $psrLogger = $this->createMock('Psr\Log\NullLogger');
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(strtolower($levelName), 'dummy', $context);

        $handler = new PsrHandler($psrLogger);
        $handler->setFormatter(new LineFormatter('dummy'));
        $handler->handle(['level' => $level, 'level_name' => $levelName, 'message' => $message, 'context' => $context, 'extra' => [], 'date' => new \DateTimeImmutable()]);
    }
}
