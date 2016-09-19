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
use Monolog\Util\LocalSocket;

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
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/POST \/api\/chat.postMessage HTTP\/1.1\\r\\nHost: slack.com\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);
    }

    public function testWriteContent()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=&attachments=.*$/', $content);
    }

    public function testWriteContentUsesFormatterIfProvided()
    {
        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->socket->getOutput();

        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', false);
        $this->handler->setFormatter(new LineFormatter('foo--%message%'));
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test2'));
        $content2 = $this->socket->getOutput();

        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=test1.*$/', $content);
        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=foo--test2.*$/', $content2);
    }

    public function testWriteContentWithEmoji()
    {
        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', true, 'alien');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/icon_emoji=%3Aalien%3A$/', $content);
    }

    /**
     * @dataProvider provideLevelColors
     */
    public function testWriteContentWithColors($level, $expectedColor)
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord($level, 'test1'));

        $content = $this->socket->getOutput();
        $this->assertRegexp('/color%22%3A%22'.$expectedColor.'/', $content);
    }

    public function testWriteContentWithPlainTextMessage()
    {
        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->socket->getOutput();
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

    private function initHandlerAndSocket($token = 'myToken', $channel = 'channel1', $username = 'Monolog', $useAttachment = true, $iconEmoji = null, $useShortAttachment = false, $includeExtra = false)
    {
        $this->socket = LocalSocket::initSocket();
        $this->handler = new SlackHandler($token, $channel, $username, $useAttachment, $iconEmoji, Logger::DEBUG, true, $useShortAttachment, $includeExtra);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');

        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    public function tearDown()
    {
        unset($this->socket, $this->handler);
    }
}
