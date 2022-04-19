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
use Monolog\Test\TestCase;

function mail($to, $subject, $message, $additional_headers = null, $additional_parameters = null)
{
    $GLOBALS['mail'][] = func_get_args();
}

class NativeMailerHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mail'] = [];
    }

    public function testConstructorHeaderInjection()
    {
        $this->expectException(\InvalidArgumentException::class);

        $mailer = new NativeMailerHandler('spammer@example.org', 'dear victim', "receiver@example.org\r\nFrom: faked@attacker.org");
    }

    public function testSetterHeaderInjection()
    {
        $this->expectException(\InvalidArgumentException::class);

        $mailer = new NativeMailerHandler('spammer@example.org', 'dear victim', 'receiver@example.org');
        $mailer->addHeader("Content-Type: text/html\r\nFrom: faked@attacker.org");
    }

    public function testSetterArrayHeaderInjection()
    {
        $this->expectException(\InvalidArgumentException::class);

        $mailer = new NativeMailerHandler('spammer@example.org', 'dear victim', 'receiver@example.org');
        $mailer->addHeader(["Content-Type: text/html\r\nFrom: faked@attacker.org"]);
    }

    public function testSetterContentTypeInjection()
    {
        $this->expectException(\InvalidArgumentException::class);

        $mailer = new NativeMailerHandler('spammer@example.org', 'dear victim', 'receiver@example.org');
        $mailer->setContentType("text/html\r\nFrom: faked@attacker.org");
    }

    public function testSetterEncodingInjection()
    {
        $this->expectException(\InvalidArgumentException::class);

        $mailer = new NativeMailerHandler('spammer@example.org', 'dear victim', 'receiver@example.org');
        $mailer->setEncoding("utf-8\r\nFrom: faked@attacker.org");
    }

    public function testSend()
    {
        $to = 'spammer@example.org';
        $subject = 'dear victim';
        $from = 'receiver@example.org';

        $mailer = new NativeMailerHandler($to, $subject, $from);
        $mailer->setFormatter(new \Monolog\Formatter\LineFormatter);
        $mailer->handleBatch([]);

        // batch is empty, nothing sent
        $this->assertEmpty($GLOBALS['mail']);

        // non-empty batch
        $mailer->handle($this->getRecord(Level::Error, "Foo\nBar\r\n\r\nBaz"));
        $this->assertNotEmpty($GLOBALS['mail']);
        $this->assertIsArray($GLOBALS['mail']);
        $this->assertArrayHasKey('0', $GLOBALS['mail']);
        $params = $GLOBALS['mail'][0];
        $this->assertCount(5, $params);
        $this->assertSame($to, $params[0]);
        $this->assertSame($subject, $params[1]);
        $this->assertStringEndsWith(" test.ERROR: Foo Bar  Baz [] []\n", $params[2]);
        $this->assertSame("From: $from\r\nContent-type: text/plain; charset=utf-8\r\n", $params[3]);
        $this->assertSame('', $params[4]);
    }

    public function testMessageSubjectFormatting()
    {
        $mailer = new NativeMailerHandler('to@example.org', 'Alert: %level_name% %message%', 'from@example.org');
        $mailer->handle($this->getRecord(Level::Error, "Foo\nBar\r\n\r\nBaz"));
        $this->assertNotEmpty($GLOBALS['mail']);
        $this->assertIsArray($GLOBALS['mail']);
        $this->assertArrayHasKey('0', $GLOBALS['mail']);
        $params = $GLOBALS['mail'][0];
        $this->assertCount(5, $params);
        $this->assertSame('Alert: ERROR Foo Bar  Baz', $params[1]);
    }
}
