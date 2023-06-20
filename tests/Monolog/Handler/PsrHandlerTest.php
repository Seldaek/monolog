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
use Monolog\Test\TestCase;
use Monolog\Formatter\LineFormatter;

/**
 * @covers Monolog\Handler\PsrHandler::handle
 */
class PsrHandlerTest extends TestCase
{
    public static function logLevelProvider()
    {
        return array_map(
            fn (Level $level) => [$level->toPsrLogLevel(), $level],
            Level::cases()
        );
    }

    /**
     * @dataProvider logLevelProvider
     */
    public function testHandlesAllLevels(string $levelName, Level $level)
    {
        $message = 'Hello, world! ' . $level->value;
        $context = ['foo' => 'bar', 'level' => $level->value];

        $psrLogger = $this->createMock('Psr\Log\NullLogger');
        $psrLogger->expects($this->once())
            ->method('log')
            ->with($levelName, $message, $context);

        $handler = new PsrHandler($psrLogger);
        $handler->handle($this->getRecord($level, $message, context: $context));
    }

    public function testFormatter()
    {
        $message = 'Hello, world!';
        $context = ['foo' => 'bar'];
        $level = Level::Error;

        $psrLogger = $this->createMock('Psr\Log\NullLogger');
        $psrLogger->expects($this->once())
            ->method('log')
            ->with($level->toPsrLogLevel(), 'dummy', $context);

        $handler = new PsrHandler($psrLogger);
        $handler->setFormatter(new LineFormatter('dummy'));
        $handler->handle($this->getRecord($level, $message, context: $context, datetime: new \DateTimeImmutable()));
    }
}
