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
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SymfonyMailerHandlerTest extends \Monolog\Test\MonologTestCase
{
    /** @var MailerInterface&MockObject */
    private $mailer;

    public function setUp(): void
    {
        $this->mailer = $this
            ->getMockBuilder(MailerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->mailer);
    }

    public function testMessageCreationIsLazyWhenUsingCallback()
    {
        $this->mailer->expects($this->never())
            ->method('send');

        $callback = function () {
            throw new \RuntimeException('Email creation callback should not have been called in this test');
        };
        $handler = new SymfonyMailerHandler($this->mailer, $callback);

        $records = [
            $this->getRecord(Logger::DEBUG),
            $this->getRecord(Logger::INFO),
        ];
        $handler->handleBatch($records);
    }

    public function testMessageCanBeCustomizedGivenLoggedData()
    {
        // Wire Mailer to expect a specific Email with a customized Subject
        $expectedMessage = new Email();
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($value) use ($expectedMessage) {
                return $value instanceof Email
                    && $value->getSubject() === 'Emergency'
                    && $value === $expectedMessage;
            }));

        // Callback dynamically changes subject based on number of logged records
        $callback = function ($content, array $records) use ($expectedMessage) {
            $subject = \count($records) > 0 ? 'Emergency' : 'Normal';

            return $expectedMessage->subject($subject);
        };
        $handler = new SymfonyMailerHandler($this->mailer, $callback);

        // Logging 1 record makes this an Emergency
        $records = [
            $this->getRecord(Logger::EMERGENCY),
        ];
        $handler->handleBatch($records);
    }

    public function testMessageSubjectFormatting()
    {
        // Wire Mailer to expect a specific Email with a customized Subject
        $messageTemplate = new Email();
        $messageTemplate->subject('Alert: %level_name% %message%');
        $receivedMessage = null;

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($value) use (&$receivedMessage) {
                $receivedMessage = $value;

                return true;
            }));

        $handler = new SymfonyMailerHandler($this->mailer, $messageTemplate);

        $records = [
            $this->getRecord(Logger::EMERGENCY),
        ];
        $handler->handleBatch($records);

        $this->assertEquals('Alert: EMERGENCY test', $receivedMessage->getSubject());
    }
}
