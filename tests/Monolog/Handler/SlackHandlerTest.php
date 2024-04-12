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
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\Slack\SlackRecord;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @see    https://api.slack.com/
 */
class SlackHandlerTest extends TestCase
{
    /**
     * @var resource
     */
    private $res;

    private SlackHandler $handler;

    public function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl to run');
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->res);
    }

    public function testWriteHeader()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('{POST /api/chat.postMessage HTTP/1.1\\r\\nHost: slack.com\\r\\nContent-Type: application/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n}', $content);
    }

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/username=Monolog/', $content);
        $this->assertMatchesRegularExpression('/channel=channel1/', $content);
        $this->assertMatchesRegularExpression('/token=myToken/', $content);
        $this->assertMatchesRegularExpression('/attachments/', $content);
    }

    public function testWriteContentUsesFormatterIfProvided()
    {
        $this->createHandler('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->createHandler('myToken', 'channel1', 'Monolog', false);
        $this->handler->setFormatter(new LineFormatter('foo--%message%'));
        $this->handler->handle($this->getRecord(Level::Critical, 'test2'));
        fseek($this->res, 0);
        $content2 = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/text=test1/', $content);
        $this->assertMatchesRegularExpression('/text=foo--test2/', $content2);
    }

    public function testWriteContentWithEmoji()
    {
        $this->createHandler('myToken', 'channel1', 'Monolog', true, 'alien');
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/icon_emoji=%3Aalien%3A/', $content);
    }

    #[DataProvider('provideLevelColors')]
    public function testWriteContentWithColors($level, $expectedColor)
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord($level, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/%22color%22%3A%22'.$expectedColor.'/', $content);
    }

    public function testWriteContentWithPlainTextMessage()
    {
        $this->createHandler('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/text=test1/', $content);
    }

    public static function provideLevelColors()
    {
        return [
            [Level::Debug,    urlencode(SlackRecord::COLOR_DEFAULT)],
            [Level::Info,     SlackRecord::COLOR_GOOD],
            [Level::Notice,   SlackRecord::COLOR_GOOD],
            [Level::Warning,  SlackRecord::COLOR_WARNING],
            [Level::Error,    SlackRecord::COLOR_DANGER],
            [Level::Critical, SlackRecord::COLOR_DANGER],
            [Level::Alert,    SlackRecord::COLOR_DANGER],
            [Level::Emergency,SlackRecord::COLOR_DANGER],
        ];
    }

    private function createHandler($token = 'myToken', $channel = 'channel1', $username = 'Monolog', $useAttachment = true, $iconEmoji = null, $useShortAttachment = false, $includeExtra = false)
    {
        $constructorArgs = [$token, $channel, $username, $useAttachment, $iconEmoji, Level::Debug, true, $useShortAttachment, $includeExtra];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder('Monolog\Handler\SlackHandler')
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->willReturn($this->res);
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->willReturn(true);
        $this->handler->expects($this->any())
            ->method('closeSocket');

        $this->handler->setFormatter($this->getIdentityFormatter());
    }
}
