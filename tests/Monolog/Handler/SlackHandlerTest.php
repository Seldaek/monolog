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
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->closeSocket();
        $this->assertRegexp('/POST \/api\/chat.postMessage HTTP\/1.1\\r\\nHost: slack.com\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);
    }

    public function testWriteContent()
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->closeSocket();
        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=&attachments=.*$/', $content);
    }

    public function testWriteContentUsesFormatterIfProvided()
    {
        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));
        $content = $this->closeSocket();

        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', false);
        $this->handler->setFormatter(new LineFormatter('foo--%message%'));
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test2'));
        $content2 = $this->closeSocket();

        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=test1.*$/', $content);
        $this->assertRegexp('/token=myToken&channel=channel1&username=Monolog&text=foo--test2.*$/', $content2);
    }

    public function testWriteContentWithEmoji()
    {
        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', true, 'alien');
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->closeSocket();
        $this->assertRegexp('/icon_emoji=%3Aalien%3A$/', $content);
    }

    /**
     * @dataProvider provideLevelColors
     */
    public function testWriteContentWithColors($level, $expectedColor)
    {
        $this->initHandlerAndSocket();
        $this->handler->handle($this->getRecord($level, 'test1'));

        $content = $this->closeSocket();
        $this->assertRegexp('/color%22%3A%22'.$expectedColor.'/', $content);
    }

    public function testWriteContentWithPlainTextMessage()
    {
        $this->initHandlerAndSocket('myToken', 'channel1', 'Monolog', false);
        $this->handler->handle($this->getRecord(Logger::CRITICAL, 'test1'));

        $content = $this->closeSocket();
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
        $tmpFile = sys_get_temp_dir().'/monolog-test-socket.php';
        file_put_contents($tmpFile, <<<'SCRIPT'
<?php

$sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
socket_bind($sock, '127.0.0.1', 51984);
socket_listen($sock);

while (true) {
    $res = socket_accept($sock);
    socket_set_option($res, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 500));
    while ($read = socket_read($res, 1024)) {
        echo $read;
    }
    socket_close($res);
}
SCRIPT
);

        $this->socket = new \Symfony\Component\Process\Process(escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmpFile));
        $this->socket->start();

        $this->handler = new SlackHandler($token, $channel, $username, $useAttachment, $iconEmoji, Logger::DEBUG, true, $useShortAttachment, $includeExtra);

        $reflectionProperty = new \ReflectionProperty('\Monolog\Handler\SocketHandler', 'connectionString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->handler, '127.0.0.1:51984');

        $this->handler->setFormatter($this->getIdentityFormatter());
    }

    private function closeSocket()
    {
        $this->socket->stop();

        return $this->socket->getOutput();
    }

    public function tearDown()
    {
        if (isset($this->socket)) {
            $this->closeSocket();
            unset($this->socket);
        }
    }
}
