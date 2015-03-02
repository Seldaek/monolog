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

        $records = [
            $this->getRecord(Logger::DEBUG),
            $this->getRecord(Logger::INFO),
        ];
        $handler->handleBatch($records);
    }
}
