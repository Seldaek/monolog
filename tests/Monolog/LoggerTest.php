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
    public function testLogAll()
    {
        $logger = new Logger();
        $log1 = $this->getMock('Monolog\Log', array('log'), array('a'));
        $log1->expects($this->once())
            ->method('log');
        $log2 = $this->getMock('Monolog\Log', array('log'), array('b'));
        $log2->expects($this->once())
            ->method('log');
        $logger->addLog($log1);
        $logger->addLog($log2);
        $logger->warn('test');
    }

    public function testLogFiltered()
    {
        $logger = new Logger();
        $log1 = $this->getMock('Monolog\Log', array('log'), array('a'));
        $log1->expects($this->exactly(2))
            ->method('log');
        $log2 = $this->getMock('Monolog\Log', array('log'), array('b'));
        $log2->expects($this->never())
            ->method('log');
        $logger->addLog($log1);
        $logger->addLog($log2);

        $logger->warn('test', 'a');
        $logger->warn('test', array('a'));
    }
}
