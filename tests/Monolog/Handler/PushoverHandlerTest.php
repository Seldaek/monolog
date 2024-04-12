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
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Almost all examples (expected header, titles, messages) taken from
 * https://www.pushover.net/api
 * @author Sebastian Göttschkes <sebastian.goettschkes@googlemail.com>
 * @see https://www.pushover.net/api
 */
class PushoverHandlerTest extends TestCase
{
    /** @var resource */
    private $res;
    private PushoverHandler&MockObject $handler;

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->res);
    }

    public function testWriteHeader()
    {
        $this->createHandler();
        $this->handler->setHighPriorityLevel(Level::Emergency); // skip priority notifications
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/POST \/1\/messages.json HTTP\/1.1\\r\\nHost: api.pushover.net\\r\\nContent-Type: application\/x-www-form-urlencoded\\r\\nContent-Length: \d{2,4}\\r\\n\\r\\n/', $content);

        return $content;
    }

    /**
     * @depends testWriteHeader
     */
    public function testWriteContent($content)
    {
        $this->assertMatchesRegularExpression('/token=myToken&user=myUser&message=test1&title=Monolog&timestamp=\d{10}$/', $content);
    }

    public function testWriteWithComplexTitle()
    {
        $this->createHandler('myToken', 'myUser', 'Backup finished - SQL1');
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/title=Backup\+finished\+-\+SQL1/', $content);
    }

    public function testWriteWithComplexMessage()
    {
        $this->createHandler();
        $this->handler->setHighPriorityLevel(Level::Emergency); // skip priority notifications
        $this->handler->handle($this->getRecord(Level::Critical, 'Backup of database "example" finished in 16 minutes.'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/message=Backup\+of\+database\+%22example%22\+finished\+in\+16\+minutes\./', $content);
    }

    public function testWriteWithTooLongMessage()
    {
        $message = str_pad('test', 520, 'a');
        $this->createHandler();
        $this->handler->setHighPriorityLevel(Level::Emergency); // skip priority notifications
        $this->handler->handle($this->getRecord(Level::Critical, $message));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $expectedMessage = substr($message, 0, 505);

        $this->assertMatchesRegularExpression('/message=' . $expectedMessage . '&title/', $content);
    }

    public function testWriteWithHighPriority()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Critical, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/token=myToken&user=myUser&message=test1&title=Monolog&timestamp=\d{10}&priority=1$/', $content);
    }

    public function testWriteWithEmergencyPriority()
    {
        $this->createHandler();
        $this->handler->handle($this->getRecord(Level::Emergency, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/token=myToken&user=myUser&message=test1&title=Monolog&timestamp=\d{10}&priority=2&retry=30&expire=25200$/', $content);
    }

    public function testWriteToMultipleUsers()
    {
        $this->createHandler('myToken', ['userA', 'userB']);
        $this->handler->handle($this->getRecord(Level::Emergency, 'test1'));
        fseek($this->res, 0);
        $content = fread($this->res, 1024);

        $this->assertMatchesRegularExpression('/token=myToken&user=userA&message=test1&title=Monolog&timestamp=\d{10}&priority=2&retry=30&expire=25200POST/', $content);
        $this->assertMatchesRegularExpression('/token=myToken&user=userB&message=test1&title=Monolog&timestamp=\d{10}&priority=2&retry=30&expire=25200$/', $content);
    }

    private function createHandler($token = 'myToken', $user = 'myUser', $title = 'Monolog')
    {
        $constructorArgs = [$token, $user, $title];
        $this->res = fopen('php://memory', 'a');
        $this->handler = $this->getMockBuilder(PushoverHandler::class)
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
