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

use Gelf\Message;
use Monolog\Test\TestCase;
use Monolog\Level;
use Monolog\Formatter\GelfMessageFormatter;

class GelfHandlerTest extends TestCase
{
    public function setUp(): void
    {
        if (!class_exists('Gelf\Publisher') || !class_exists('Gelf\Message')) {
            $this->markTestSkipped("graylog2/gelf-php not installed");
        }
    }

    /**
     * @covers Monolog\Handler\GelfHandler::__construct
     */
    public function testConstruct()
    {
        $handler = new GelfHandler($this->getMessagePublisher());
        $this->assertInstanceOf('Monolog\Handler\GelfHandler', $handler);
    }

    protected function getHandler($messagePublisher)
    {
        $handler = new GelfHandler($messagePublisher);

        return $handler;
    }

    protected function getMessagePublisher()
    {
        return $this->getMockBuilder('Gelf\Publisher')
            ->onlyMethods(['publish'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testDebug()
    {
        $record = $this->getRecord(Level::Debug, "A test debug message");
        $expectedMessage = new Message();
        $expectedMessage
            ->setLevel(7)
            ->setAdditional('facility', 'test')
            ->setShortMessage($record->message)
            ->setTimestamp($record->datetime)
        ;

        $messagePublisher = $this->getMessagePublisher();
        $messagePublisher->expects($this->once())
            ->method('publish')
            ->with($expectedMessage);

        $handler = $this->getHandler($messagePublisher);

        $handler->handle($record);
    }

    public function testWarning()
    {
        $record = $this->getRecord(Level::Warning, "A test warning message");
        $expectedMessage = new Message();
        $expectedMessage
            ->setLevel(4)
            ->setAdditional('facility', 'test')
            ->setShortMessage($record->message)
            ->setTimestamp($record->datetime)
        ;

        $messagePublisher = $this->getMessagePublisher();
        $messagePublisher->expects($this->once())
            ->method('publish')
            ->with($expectedMessage);

        $handler = $this->getHandler($messagePublisher);

        $handler->handle($record);
    }

    public function testInjectedGelfMessageFormatter()
    {
        $record = $this->getRecord(
            Level::Warning,
            "A test warning message",
            extra: ['blarg' => 'yep'],
            context: ['from' => 'logger'],
        );

        $expectedMessage = new Message();
        $expectedMessage
            ->setLevel(4)
            ->setAdditional('facility', 'test')
            ->setHost("mysystem")
            ->setShortMessage($record->message)
            ->setTimestamp($record->datetime)
            ->setAdditional("EXTblarg", 'yep')
            ->setAdditional("CTXfrom", 'logger')
        ;

        $messagePublisher = $this->getMessagePublisher();
        $messagePublisher->expects($this->once())
            ->method('publish')
            ->with($expectedMessage);

        $handler = $this->getHandler($messagePublisher);
        $handler->setFormatter(new GelfMessageFormatter('mysystem', 'EXT', 'CTX'));
        $handler->handle($record);
    }
}
