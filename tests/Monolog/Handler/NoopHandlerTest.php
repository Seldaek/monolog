<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Roel Harbers <roelharbers@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Test\TestCase;
use Monolog\Logger;

/**
 * @covers Monolog\Handler\NoopHandler::handle
 */
class NoopHandlerTest extends TestCase
{
    /**
     * @dataProvider logLevelsProvider
     */
    public function testIsHandling($level)
    {
        $handler = new NoopHandler();
        $this->assertTrue($handler->isHandling($this->getRecord($level)));
    }

    public function isHandleProvider()
    {
        return array_map(
            function ($level) {
                return [$level];
            },
            array_values(Logger::getLevels())
        );
    }

    /**
     * @dataProvider logLevelsProvider
     */
    public function testHandle($level)
    {
        $handler = new NoopHandler();
        $this->assertFalse($handler->handle($this->getRecord($level)));
    }

    public function logLevelsProvider()
    {
        return array_map(
            function ($level) {
                return [$level];
            },
            array_values(Logger::getLevels())
        );
    }
}
