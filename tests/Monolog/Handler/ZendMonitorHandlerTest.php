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

class ZendMonitorHandlerTest extends TestCase
{
    protected $zendMonitorHandler;

    public function setUp()
    {
        if (!function_exists('zend_monitor_custom_event')) {
            $this->markTestSkipped('ZendServer is not installed');
        }
    }

    /**
     * @covers  \Monolog\Handler\ZendMonitor::__construct
     * @covers  \Monolog\Handler\ZendMonitor::isZendServer
     */
    public function testIsZendServerReturnsTrue()
    {
        $zendMonitor = new ZendMonitorHandler();
        $this->assertTrue($zendMonitor->isZendServer());
    }

    /**
     * @covers  \Monolog\Handler\ZendMonitor::write
     */
    public function testWrite()
    {
        $record = $this->getRecord();

        $zendMonitor = $this->getMockBuilder('Monolog\Handler\ZendMonitorHandler')
                            ->setMethods(array('writeZendMonitorCustomEvent'))
                            ->getMock();

        $levelMap = $zendMonitor->getLevelMap();

        $zendMonitor->expects($this->once())
                    ->method('writeZendMonitorCustomEvent')
                    ->with($levelMap[$record['level']], $record['message']);

        $zendMonitor->handle($record);
    }

    public function testGetDefaultFormatterReturnsNormalizerFormatter()
    {
        $zendMonitor = new ZendMonitorHandler();
        $this->assertInstanceOf('Monolog\Formatter\NormalizerFormatter', $zendMonitor->getDefaultFormatter());
    }
}
