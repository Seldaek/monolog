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
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Mazur Alexandr <alexandrmazur96@gmail.com>
 * @link https://core.telegram.org/bots/api
 */
class TelegramBotHandlerTest extends \Monolog\Test\MonologTestCase
{
    private TelegramBotHandler&MockObject $handler;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->handler);
    }

    public function testSendTelegramRequest(): void
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord());
    }

    private function createHandler(
        string $apiKey = 'testKey',
        string $channel = 'testChannel',
        string $parseMode = 'Markdown',
        bool $disableWebPagePreview = false,
        bool $disableNotification = true,
        int $topic = 1
    ): void {
        $constructorArgs = [$apiKey, $channel, Level::Debug, true, $parseMode, $disableWebPagePreview, $disableNotification, $topic];

        $this->handler = $this->getMockBuilder(TelegramBotHandler::class)
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['send'])
            ->getMock();

        $this->handler->expects($this->atLeast(1))
            ->method('send');
    }

    public function testSetInvalidParseMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = new TelegramBotHandler('testKey', 'testChannel');
        $handler->setParseMode('invalid parse mode');
    }

    public function testSetParseMode(): void
    {
        $handler = new TelegramBotHandler('testKey', 'testChannel');
        $handler->setParseMode('HTML');
    }
}
