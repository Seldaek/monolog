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

use Monolog\TestCase;
use Monolog\Logger;

class FirePHPHandlerTest extends TestCase
{

    /**
     * @dataProvider handlerProvider
     */
    public function testCloseReturnsHeadersSent($handler)
    {
        $this->assertEquals(headers_sent(), $handler->close());
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testDefaultWriterIsClosure($handler)
    {
        $this->assertEquals('header', $handler->getWriter());
    }

    public function testConstructWithWriter()
    {
        $writer = array($this, 'testWriter');
        
        $handler = new FirePHPHandler(Logger::DEBUG, false, $writer);
        
        $this->assertEquals($writer, $handler->getWriter());
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testWriterIsSettable($handler)
    {
        $writer = array($this, 'testWriter');
        $handler->setWriter($writer);
        
        $this->assertNotEquals('header', $handler->getWriter());
        $this->assertEquals($writer, $handler->getWriter());
    }

    public function testMethodWriter()
    {
        $handler = new FirePHPHandler;
        $handler->setWriter(array($this, 'writerForTestMethodWriter'));
        
        $handler->handle($this->getRecord(Logger::DEBUG));
    }

    public function writerForTestMethodWriter($header)
    {
        $valid = array(
            'X-Wf-Protocol-1: http://meta.wildfirehq.org/Protocol/JsonStream/0.2',
            'X-Wf-1-Structure-1: http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1',
            'X-Wf-1-Plugin-1: http://meta.firephp.org/Wildfire/Plugin/ZendFramework/FirePHP/1.6.2',
            'X-Wf-1-1-1-5: 50|[{"Type":"LOG","File":"","Line":""},"test: test "]|',
        );
        
        $this->assertTrue(in_array($header, $valid));
    }

    public function testClosureWriter()
    {
        $headers = array();
        
        $handler = new FirePHPHandler;
        $handler->setWriter(function($header) use (&$headers) {
            $headers[] = $header;
        });
        
        $handler->handle($this->getRecord(Logger::DEBUG));
        
        $this->assertEquals(
            'X-Wf-1-1-1-5: 50|[{"Type":"LOG","File":"","Line":""},"test: test "]|',
            end($headers)
        );
        
        $this->assertEquals(4, count($headers), "There should be 3 init headers & 1 message header");
    }

    public function handlerProvider()
    {
        $handler = new FirePHPHandler();
        
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::DEBUG));
        $handler->handle($this->getRecord(Logger::INFO));
        $handler->handle($this->getRecord(Logger::WARNING));
        
        return array(
            array($handler),
        );
    }
}
