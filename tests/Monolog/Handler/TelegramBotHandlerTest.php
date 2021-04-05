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

use Generator;
use Monolog\Logger;
use Monolog\Test\TestCase;

/**
 * @author Mazur Alexandr <alexandrmazur96@gmail.com>
 * @link https://core.telegram.org/bots/api
 */
class TelegramBotHandlerTest extends TestCase
{
    /** @var TelegramBotHandler */
    private $handler;

    public function testSendTelegramRequest(): void
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord());
    }

    /**
     * @dataProvider tooLongMessageHandlingProvider
     * @param string $strategy
     * @param int $messageLength
     * @param int $expectedRequestsCounts
     * @return void
     */
    public function testTooLongMessageHandling(string $strategy, int $messageLength, int $expectedRequestsCounts)
    {
        $this->createHandler(
            'testKey',
            'testChannel',
            'HTML',
            false,
            false,
            $strategy,
            $expectedRequestsCounts
        );

        $message = str_repeat('A', $messageLength);

        $this->handler->handle(
            $this->getRecord(
                Logger::WARNING,
                $message
            )
        );
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

    /** @return Generator */
    public function tooLongMessageHandlingProvider(): Generator
    {
        yield 'message-not-long' => [TelegramBotHandler::SPLIT_TOO_LONG_MESSAGE_INTO_MULTIPLE_MESSAGES, 4095, 1];
        yield 'split-long-message-into-multiple-messages' => [
            TelegramBotHandler::SPLIT_TOO_LONG_MESSAGE_INTO_MULTIPLE_MESSAGES,
            8192,
            2,
        ];
        yield 'trim-too-long-message' => [TelegramBotHandler::TRIM_TOO_LONG_MESSAGE, 8192, 1];

        // because we can not mock curl request we can't expect exception here, but at least we should be sure that
        // method send() be called once.
        yield 'do-nothing-with-long-message' => [TelegramBotHandler::DO_NOTHING_WITH_TOO_LONG_MESSAGE, 8192, 1];
        yield 'unexpected-strategy-provided' => ['bla-bla', 8192, 1];
    }

    /**
     * @param string $apiKey
     * @param string $channel
     */
    private function createHandler(
        string $apiKey = 'testKey',
        string $channel = 'testChannel',
        string $parseMode = 'Markdown',
        bool $disableWebPagePreview = false,
        bool $disableNotification = true,
        string $tooLongMessageHandlingStrategy = TelegramBotHandler::DO_NOTHING_WITH_TOO_LONG_MESSAGE,
        int $methodSendCallsCount = 1
    ): void {
        $constructorArgs = [
            $apiKey,
            $channel,
            Logger::DEBUG,
            true,
            $parseMode,
            $disableWebPagePreview,
            $disableNotification,
            $tooLongMessageHandlingStrategy,
        ];

        $this->handler = $this->getMockBuilder(TelegramBotHandler::class)
            ->setConstructorArgs($constructorArgs)
            ->setMethods(['send'])
            ->getMock();

        $this->handler->expects($this->atLeast($methodSendCallsCount))
            ->method('send');
    }
}
