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

class LogTest extends \PHPUnit_Framework_TestCase
{
    public function testLog()
    {
        $logger = new Log('bob');
        $writer1 = $this->getMock('Monolog\Writer\NullWriter', array('write'));
        $writer1->expects($this->once())
            ->method('write')
            ->with('bob', Logger::WARN, 'test');
        $writer2 = $this->getMock('Monolog\Writer\NullWriter', array('write'));
        $writer2->expects($this->once())
            ->method('write')
            ->with('bob', Logger::WARN, 'test');
        $logger->addWriter($writer1);
        $logger->addWriter($writer2);
        $logger->log(Logger::WARN, 'test');
    }

    public function testLogLowLevel()
    {
        $logger = new Log('bob');
        $logger->setLevel(Logger::FATAL);
        $this->assertEquals(Logger::FATAL, $logger->getLevel());

        $writer1 = $this->getMock('Monolog\Writer\NullWriter', array('write'));
        $writer1->expects($this->never())
            ->method('write');
        $logger->addWriter($writer1);
        $logger->log(Logger::WARN, 'test');
    }
}
