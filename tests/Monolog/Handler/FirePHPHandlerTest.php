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
    public function testCloseReturnsFalseWhenHeadersAlreadySent($handler)
    {
        $this->assertFalse($handler->close());
    }

    public function testEmptyHandlerHasProtocolStructureAndPluginHeaders()
    {
        $handler = new FirePHPHandler();
        
        $this->assertEquals(3, count($handler->getHeaders()));
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testHandlerHasWildFireAndRecordHeaders($handler)
    {
        $this->assertEquals(7, count($handler->getHeaders()));
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
