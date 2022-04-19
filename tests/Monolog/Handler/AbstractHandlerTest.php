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

class AbstractHandlerTest extends TestCase
{
    /**
     * @covers Monolog\Handler\AbstractHandler::__construct
     * @covers Monolog\Handler\AbstractHandler::getLevel
     * @covers Monolog\Handler\AbstractHandler::setLevel
     * @covers Monolog\Handler\AbstractHandler::getBubble
     * @covers Monolog\Handler\AbstractHandler::setBubble
     */
    public function testConstructAndGetSet()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler', [Level::Warning, false]);
        $this->assertEquals(Level::Warning, $handler->getLevel());
        $this->assertEquals(false, $handler->getBubble());

        $handler->setLevel(Level::Error);
        $handler->setBubble(true);
        $this->assertEquals(Level::Error, $handler->getLevel());
        $this->assertEquals(true, $handler->getBubble());
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::handleBatch
     */
    public function testHandleBatch()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler');
        $handler->expects($this->exactly(2))
            ->method('handle');
        $handler->handleBatch([$this->getRecord(), $this->getRecord()]);
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::isHandling
     */
    public function testIsHandling()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler', [Level::Warning, false]);
        $this->assertTrue($handler->isHandling($this->getRecord()));
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Debug)));
    }

    /**
     * @covers Monolog\Handler\AbstractHandler::__construct
     */
    public function testHandlesPsrStyleLevels()
    {
        $handler = $this->getMockForAbstractClass('Monolog\Handler\AbstractHandler', ['warning', false]);
        $this->assertFalse($handler->isHandling($this->getRecord(Level::Debug)));
        $handler->setLevel('debug');
        $this->assertTrue($handler->isHandling($this->getRecord(Level::Debug)));
    }
}
