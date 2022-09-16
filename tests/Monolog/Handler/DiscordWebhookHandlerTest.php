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
 * @author Erfan Ebrahimi <me@erfnebrahimmi.ir> [https://erfanebrahimi.ir]
 */
class DiscordWebhookHandlerTest extends TestCase
{
    /**
     * @var DiscordWebhookHandler
     */
    private $handler;

    public function testSendDiscordRequest(): void
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord());
    }

    private function createHandler(
        string $WEBHOOK_URL = 'testKey',
        string $parseMode = 'Markdown',
        string $username = 'Log',
        string $avatar_url = 'https://cdn.discordapp.com/attachments/667370472828043284/1020287124597133332/log.png',
        bool $tts = false
    ): void {
        $constructorArgs = [$WEBHOOK_URL, Logger::DEBUG, true, $parseMode, $username, $avatar_url,$tts];

        $this->handler = $this->getMockBuilder(DiscordWebhookHandler::class)
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['send'])
            ->getMock();

        $this->handler->expects($this->atLeast(1))
            ->method('send');
    }

    public function testSetInvalidParseMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = new DiscordWebhookHandler('testKey');
        $handler->setParseMode('invalid parse mode');
    }

    public function testSetParseMode(): void
    {
        $handler = new DiscordWebhookHandler('testKey');
        $handler->setParseMode('Markdown');
    }
}
