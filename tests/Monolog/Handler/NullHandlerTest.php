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

/**
 * @covers Monolog\Handler\NullHandler::handle
 */
class NullHandlerTest extends \Monolog\Test\MonologTestCase
{
    public function testHandle()
    {
        $handler = new NullHandler();
        $this->assertTrue($handler->handle($this->getRecord()));
    }

    public function testHandleLowerLevelRecord()
    {
        $handler = new NullHandler(Level::Warning);
        $this->assertFalse($handler->handle($this->getRecord(Level::Debug)));
    }

    public function testSerializeRestorePrivate()
    {
        $handler = new NullHandler(Level::Warning);
        self::assertFalse($handler->handle($this->getRecord(Level::Debug)));
        self::assertTrue($handler->handle($this->getRecord(Level::Warning)));

        $handler = unserialize(serialize($handler));
        self::assertFalse($handler->handle($this->getRecord(Level::Debug)));
        self::assertTrue($handler->handle($this->getRecord(Level::Warning)));
    }
}
