<?php

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
use Monolog\TestCase;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 * @covers Monolog\Handler\SwiftMailerHandler
 */
class SwiftMailerHandlerTest extends TestCase
{
    private $mailer;

    public function setUp()
    {
        $this->mailer = $this
            ->getMockBuilder('Swift_Mailer')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @dataProvider sendProvider
     */
    public function testSend($message)
    {
        $handler = new SwiftMailerHandler($this->mailer, $message);
        $this->send($handler, is_callable($message) ? $message() : $message);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorSafeGuard()
    {
        new SwiftMailerHandler($this->mailer, null);
    }


    public function sendProvider()
    {
        $message = new \Swift_Message();
        return array(
            array(new \Swift_Message()),
            array(function () use ($message) { return $message; }), // to make sure we have the same instance every call
        );
    }

    private function send(SwiftMailerHandler $handler, \Swift_Message $message)
    {
        $newBody = 'henk';

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($value) use ($message, $newBody) {
                return $value instanceof \Swift_Message && $value !== $message && $value->getBody() === $newBody;
            }));

        $oldBody = $message->getBody();

        $c = new \ReflectionClass($handler);
        $m = $c->getMethod('send');
        $m->setAccessible(true);
        $m->invoke($handler, $newBody, array());
        $m->setAccessible(false);

        $this->assertEquals($oldBody, $message->getBody());
    }
}
