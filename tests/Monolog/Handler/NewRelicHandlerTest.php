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

class NewRelicHandlerTest extends TestCase
{
    public function testFallbackHandler()
    {
        $handler         = new NewRelicHandler();
        $fallbackHandler = new TestHandler();
        $handler->setFallbackHandler($fallbackHandler);
        $record          = array(
            'level' => Logger::DEBUG,
            'extra' => array(),
        );
        
        $handler->handle($record);
        
        $this->assertCount(1, $fallbackHandler->getRecords());
    }
}
