<?php

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\TestCase;

class SwiftMailerHandlerTest extends TestCase
{
    /** @var \Swift_Mailer|\PHPUnit_Framework_MockObject_MockObject */
    private $mailer;

    public function setUp()
    {
        $this->mailer = $this
            ->getMockBuilder('Swift_Mailer')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testMessageCreationIsLazyWhenUsingCallback()
    {
        $this->mailer->expects($this->never())
            ->method('send');

        $callback = function () {
            throw new \RuntimeException('Swift_Message creation callback should not have been called in this test');
        };
        $handler = new SwiftMailerHandler($this->mailer, $callback);

        $records = array(
            $this->getRecord(Logger::DEBUG),
            $this->getRecord(Logger::INFO),
        );
        $handler->handleBatch($records);
    }

    public function testMessageCanBeCustomizedGivenLoggedData()
    {
        // Wire Mailer to expect a specific Swift_Message with a customized Subject
        $expectedMessage = new \Swift_Message();
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($value) use ($expectedMessage) {
                return $value instanceof \Swift_Message
                    && $value->getSubject() === 'Emergency'
                    && $value === $expectedMessage;
            }));

        // Callback dynamically changes subject based on number of logged records
        $callback = function ($content, array $records) use ($expectedMessage) {
            $subject = count($records) > 0 ? 'Emergency' : 'Normal';
            $expectedMessage->setSubject($subject);

            return $expectedMessage;
        };
        $handler = new SwiftMailerHandler($this->mailer, $callback);

        // Logging 1 record makes this an Emergency
        $records = array(
            $this->getRecord(Logger::EMERGENCY),
        );
        $handler->handleBatch($records);
    }
}
