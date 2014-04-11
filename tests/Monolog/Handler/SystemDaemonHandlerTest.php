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

use Monolog\Logger;
use Monolog\TestCase;
use ReflectionProperty;

class SystemDaemonHandlerTest extends TestCase
{
    protected function setup()
    {
        if (! class_exists('System_Daemon')) {
            $this->markTestSkipped('The "pear/system_daemon" package is not installed');
        }
    }

    public function logLevelsProvider()
    {
        return array(
            array(Logger::EMERGENCY, 'emerg'),
            array(Logger::ALERT, 'alert'),
            array(Logger::CRITICAL, 'crit'),
            array(Logger::ERROR, 'err'),
            array(Logger::WARNING, 'warning'),
            array(Logger::NOTICE, 'notice'),
            array(Logger::INFO, 'info'),
            array(Logger::DEBUG, 'debug'),
        );
    }

    /**
     * @dataProvider logLevelsProvider
     */
    public function testShouldLogUsingSystemDaemon($level, $methodName)
    {
        $record = $this->getRecord($level, 'test', array('data' => new \stdClass, 'foo' => 34));

        $systemDaemonMock = $this->getMockClass('System_Daemon', array($methodName));
        $systemDaemonMock::staticExpects($this->once())
            ->method($methodName)
            ->with('(test) test {"data":"[object] (stdClass: {})","foo":34} []');

        $handler = new SystemDaemonHandler();
        $reflection = new ReflectionProperty($handler, 'systemDaemonClassName');
        $reflection->setAccessible(true);
        $reflection->setValue($handler, $systemDaemonMock);

        $handler->handle($record);
    }
}
