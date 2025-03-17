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

function mail($to, $subject, $message, $additional_headers = null, $additional_parameters = null)
{
    $GLOBALS['mail'][] = \func_get_args();
}

class NativeMailerHandlerTest extends \Monolog\Test\MonologTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mail'] = [];
    }

    protected function newNativeMailerHandler(... $args) : NativeMailerHandler
    {
        return new class(... $args) extends NativeMailerHandler {
            public $mail = [];

            protected function mail(
                string $to,
                string $subject,
                string $content,
                string $headers,
                string $parameters
            ) : void {
                $this->mail[] = \func_get_args();
            }
        };
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

        $mailer = $this->newNativeMailerHandler($to, $subject, $from);
        $mailer->setFormatter(new \Monolog\Formatter\LineFormatter);
        $mailer->handleBatch([]);

        // batch is empty, nothing sent
        $this->assertEmpty($mailer->mail);

        // non-empty batch
        $mailer->handle($this->getRecord(Level::Error, "Foo\nBar\r\n\r\nBaz"));
        $this->assertNotEmpty($mailer->mail);
        $this->assertIsArray($mailer->mail);
        $this->assertArrayHasKey('0', $mailer->mail);
        $params = $mailer->mail[0];
        $this->assertCount(5, $params);
        $this->assertSame($to, $params[0]);
        $this->assertSame($subject, $params[1]);
        $this->assertStringEndsWith(" test.ERROR: Foo Bar  Baz [] []\n", $params[2]);
        $this->assertSame("From: $from\r\nContent-type: text/plain; charset=utf-8\r\n", $params[3]);
        $this->assertSame('', $params[4]);
    }

    public function testMessageSubjectFormatting()
    {
        $mailer = $this->newNativeMailerHandler('to@example.org', 'Alert: %level_name% %message%', 'from@example.org');
        $mailer->handle($this->getRecord(Level::Error, "Foo\nBar\r\n\r\nBaz"));
        $this->assertNotEmpty($mailer->mail);
        $this->assertIsArray($mailer->mail);
        $this->assertArrayHasKey('0', $mailer->mail);
        $params = $mailer->mail[0];
        $this->assertCount(5, $params);
        $this->assertSame('Alert: ERROR Foo Bar  Baz', $params[1]);
    }

    public function testMail()
    {
        $mailer = new NativeMailerHandler('to@example.org', 'subject', 'from@example.org');
        $mailer->addParameter('foo');
        $mailer->handle($this->getRecord(Level::Error, "FooBarBaz"));
        $this->assertNotEmpty($GLOBALS['mail']);
        $this->assertIsArray($GLOBALS['mail']);
        $this->assertArrayHasKey('0', $GLOBALS['mail']);
        $params = $GLOBALS['mail'][0];
        $this->assertCount(5, $params);
        $this->assertSame('to@example.org', $params[0]);
        $this->assertSame('subject', $params[1]);
        $this->assertStringContainsString("FooBarBaz", $params[2]);
        $this->assertStringContainsString('From: from@example.org', $params[3]);
        $this->assertSame('foo', $params[4]);
    }
}
