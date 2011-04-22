<?php

namespace Monolog\Handler;

use Monolog\Logger;

use Monolog\TestCase;

class MailHandlerTest extends TestCase
{
    
    public function testHandleBatch()
    {
        $records = $this->getMultipleRecords();
        
        $formatter = $this->getMock('Monolog\Formatter\LineFormatter');
        $formatter->expects($this->exactly(count($records)))
            ->method('format'); // Each record is formatted
        
        $handler = $this->getMockForAbstractClass('Monolog\\Handler\\MailHandler');
        $handler->expects($this->once())
            ->method('send');
        $handler->expects($this->never())
            ->method('write'); // write is for individual records
        
        $handler->setFormatter($formatter);
        
        $handler->handleBatch($records);
    }
    
    public function testHandle()
    {
        $record = $this->getRecord();
        
        $handler = $this->getMockForAbstractClass('Monolog\\Handler\\MailHandler');
        $handler->expects($this->once())
            ->method('write');
            
        $this->assertTrue($handler->handle($record));
    }
    
}