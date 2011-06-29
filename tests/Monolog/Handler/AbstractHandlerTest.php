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

class AbstractHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\AbstractHandler::isHandling
     */
    public function testIsHandling()
    {
        $handler = new TestHandler(Logger::WARNING, false);
        $this->assertTrue($handler->isHandling($this->getRecord()));
        $this->assertFalse($handler->isHandling($this->getRecord(Logger::DEBUG)));
    }
}
