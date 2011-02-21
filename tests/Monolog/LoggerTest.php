<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testLog()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMock('Monolog\Handler\NullHandler', array('handle'));
        $handler->expects($this->once())
            ->method('handle');
        $logger->pushHandler($handler);

        $this->assertTrue($logger->addWarning('test'));
    }

    public function testLogNoHandler()
    {
        $logger = new Logger(__METHOD__);

        $handler = $this->getMock('Monolog\Handler\NullHandler', array('handle'), array(Logger::ERROR));
        $handler->expects($this->never())
            ->method('handle');
        $logger->pushHandler($handler);

        $this->assertFalse($logger->addWarning('test'));
    }

    public function logValues()
    {
        return array(array(true), array(false));
    }

    public function testPushPopHandler()
    {
        $logger = new Logger(__METHOD__);
        $handler1 = $this->getMock('Monolog\Handler\NullHandler', array('handle'));
        $handler2 = $this->getMock('Monolog\Handler\NullHandler', array('handle'));
        $handler3 = $this->getMock('Monolog\Handler\NullHandler', array('handle'));

        $logger->pushHandler($handler1);
        $logger->pushHandler($handler2);
        $logger->pushHandler($handler3);

        $this->assertEquals($handler3, $logger->popHandler());
        $this->assertEquals($handler2, $logger->popHandler());
        $this->assertEquals($handler1, $logger->popHandler());
    }
}
