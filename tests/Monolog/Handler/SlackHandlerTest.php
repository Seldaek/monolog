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
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

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

    /**
     * @var SlackHandler
     */
    private $handler;

    public function setUp()
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires openssl to run');
        }
    }

    public function testWriteHeader()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('{POST /api/chat.postMessage HTTP/1.1\\r\\nHost: slack.com\\r\\nContent-Type: application/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n}', $content);
    }

    public function testWriteContent()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=&attachments=.*$/', $content);
    }

    public function testWriteContentUsesFormatterIfProvided()
    {
        $this->createHandler('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->createHandler('myToken', 'channel1', 'Monolog', false);
        $this->handler->setFormatter(new LineFormatter('foo--%message%'));
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test2'));
        fseek($this->res, 0);
        $content2 = fread($this->res, 1024);

        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=test1.*$/', $content);
        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=foo--test2.*$/', $content2);
    }

    public function testWriteContentWithEmoji()
    {
        $this->createHandler('myToken', 'channel1', 'Monolog', true, 'alien');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/icon_emoji=%3Aalien%3A$/', $content);
    }

    /**
     * @dataProvider provideLevelColors
     */
    public function testWriteContentWithColors($level, $expectedColor)
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord($level, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/color%22%3A%22'.$expectedColor.'/', $content);
    }

    public function testWriteContentWithPlainTextMessage()
    {
        $this->createHandler('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertRegexp('/text=test1/', $content);
    }

    public function provideLevelColors()
    {
        return [
            [Logger::DEBUG,    '%23e3e4e6'],   // escaped #e3e4e6
            [Logger::INFO,     'good'],
            [Logger::NOTICE,   'good'],
            [Logger::WARNING,  'warning'],
            [Logger::ERROR,    'danger'],
            [Logger::CRITICAL, 'danger'],
            [Logger::ALERT,    'danger'],
            [Logger::EMERGENCY,'danger'],
        ];
    }

    private function createHandler($token = 'myToken', $channel = 'channel1', $username = 'Monolog', $useAttachment = true, $iconEmoji = null, $useShortAttachment = false, $includeExtra = false)
    {
        $constructorArgs = [$token, $channel, $username, $useAttachment, $iconEmoji, Logger::DEBUG, true, $useShortAttachment, $includeExtra];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder('Monolog\Handler\SlackHandler')
            ->setConstructorArgs($constructorArgs)
            ->setMethods(['fsockopen', 'streamSetTimeout', 'closeSocket'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, 'localhost:1234');

        $this->handler->expects($this->any())
            ->method('fsockopen')
            ->will($this->returnValue($this->res));
        $this->handler->expects($this->any())
            ->method('streamSetTimeout')
            ->will($this->returnValue(true));
        $this->handler->expects($this->any())
            ->method('closeSocket')
            ->will($this->returnValue(true));

        $this->handler->setFormatter($this->getIdentityFormatter());
    }
}
