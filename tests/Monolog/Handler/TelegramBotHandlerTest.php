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

use Monolog\Logger;
use Monolog\Test\TestCase;

/**
 * @author Mazur Alexandr <alexandrmazur96@gmail.com>
 * @link https://core.telegram.org/bots/api
 */
class TelegramBotHandlerTest extends TestCase
{
    /**
     * @var TelegramBotHandler
     */
    private $handler;

    public function testSendTelegramRequest(): void
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord());
    }

    /**
     * @param string $apiKey
     * @param string $channel
     */
    private function createHandler(string $apiKey = 'testKey', string $channel = 'testChannel'): void
    {
        $constructorArgs = [$apiKey, $channel, Logger::DEBUG, true];

        $this->handler = $this->getMockBuilder(TelegramBotHandler::class)
            ->setConstructorArgs($constructorArgs)
            ->setMethods(['send'])
            ->getMock();

        $this->handler->expects($this->atLeast(1))
            ->method('send');
    }
}
