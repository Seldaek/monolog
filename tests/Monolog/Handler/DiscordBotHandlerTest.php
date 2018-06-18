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

use Monolog\Test\TestCase;
use Monolog\Logger;

/**
 * @author Gregory Goijaerts <crecketgaming@gmail.com>
 * @see    https://discordapp.com/developers/docs/reference
 * @coversDefaultClass Monolog\Handler\DiscordBotHandler
 */
class DiscordBotHandlerTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testConstructorMinimal()
    {
        $handler = new DiscordBotHandler("test-channel", 'test-token');
        $this->assertInstanceOf('Monolog\Handler\AbstractProcessingHandler', $handler);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorFull()
    {
        $handler = new DiscordBotHandler(
            "test-channel",
            "test-token",
            Logger::DEBUG,
            true,
            null,
            ":warning:"
        );
        $this->assertInstanceOf('Monolog\Handler\AbstractProcessingHandler', $handler);
    }
}
