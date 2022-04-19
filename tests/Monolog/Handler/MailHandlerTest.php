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

class MailHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\MailHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $formatter = $this->createMock('Monolog\\Formatter\\FormatterInterface');
        $formatter->expects($this->once())
            ->method('formatBatch'); // Each record is formatted

        $handler = $this->getMockForAbstractClass('Monolog\\Handler\\MailHandler', [], '', true, true, true, ['send', 'write']);
        $handler->expects($this->once())
            ->method('send');
        $handler->expects($this->never())
            ->method('write'); // write is for individual records

        $handler->setFormatter($formatter);

        $handler->handleBatch($this->getMultipleRecords());
    }

    /**
     * @covers Monolog\Handler\MailHandler::handleBatch
     */
    public function testHandleBatchNotSendsMailIfMessagesAreBelowLevel()
    {
        $records = [
            $this->getRecord(Level::Debug, 'debug message 1'),
            $this->getRecord(Level::Debug, 'debug message 2'),
            $this->getRecord(Level::Info, 'information'),
        ];

        $handler = $this->getMockForAbstractClass('Monolog\\Handler\\MailHandler');
        $handler->expects($this->never())
            ->method('send');
        $handler->setLevel(Level::Error);

        $handler->handleBatch($records);
    }

    /**
     * @covers Monolog\Handler\MailHandler::write
     */
    public function testHandle()
    {
        $handler = $this->getMockForAbstractClass('Monolog\\Handler\\MailHandler');
        $handler->setFormatter(new \Monolog\Formatter\LineFormatter);

        $record = $this->getRecord();
        $records = [$record];
        $records[0]['formatted'] = '['.$record->datetime.'] test.WARNING: test [] []'."\n";

        $handler->expects($this->once())
            ->method('send')
            ->with($records[0]['formatted'], $records);

        $handler->handle($record);
    }
}
