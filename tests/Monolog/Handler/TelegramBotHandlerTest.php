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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('apiResponseFailureProvider')]
    public function testValidateApiResponseFailures(string|bool $rawResult, string $expectedMessage): void
    {
        $handler = new TelegramBotHandler('testKey', 'testChannel');
        $invoke = (new \ReflectionClass($handler))->getMethod('validateApiResponse');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $invoke->invoke($handler, $rawResult);
    }

    /**
     * @return array<string, array{string|bool, string}>
     */
    public static function apiResponseFailureProvider(): array
    {
        return [
            'curl returned false' => [false, 'No response'],
            'non-JSON HTML body (e.g. Cloudflare 502)' => ['<html>502 Bad Gateway</html>', 'Unexpected non-JSON response'],
            'empty body' => ['', 'Unexpected non-JSON response'],
            'ok=false with description' => ['{"ok":false,"description":"Bad Request: chat not found"}', 'Bad Request: chat not found'],
            'ok=false without description' => ['{"ok":false}', 'Unknown error'],
            'ok key missing entirely' => ['{"error":"something else"}', 'Unknown error'],
        ];
    }

    public function testValidateApiResponseAcceptsSuccess(): void
    {
        $handler = new TelegramBotHandler('testKey', 'testChannel');
        $invoke = (new \ReflectionClass($handler))->getMethod('validateApiResponse');

        $invoke->invoke($handler, '{"ok":true,"result":{"message_id":1}}');

        $this->expectNotToPerformAssertions();
    }
}
