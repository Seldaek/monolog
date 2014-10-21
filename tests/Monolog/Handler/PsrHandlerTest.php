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

/**
 * @covers Monolog\Handler\PsrHandler::handle
 */
class PsrHandlerTest extends TestCase
{
    public function logLevelProvider()
    {
        $levels = [];
        $monologLogger = new Logger('', [], []);

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
        $message = 'Hello, world!';
        $context = ['foo' => 'bar'];

        $psrLogger = $this->getMock('Psr\Log\NullLogger');
        $psrLogger->expects($this->once())
            ->method(strtolower($levelName))
            ->with($message, $context);

        $handler = new PsrHandler($psrLogger);
        $handler->handle(['level' => $level, 'level_name' => $levelName, 'message' => $message, 'context' => $context]);
    }
}
