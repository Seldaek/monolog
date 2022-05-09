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

use Monolog\Test\TestCase;

class ZendMonitorHandlerTest extends TestCase
{
    public function setUp(): void
    {
        if (!function_exists('zend_monitor_custom_event')) {
            $this->markTestSkipped('ZendServer is not installed');
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->zendMonitorHandler);
    }

    /**
     * @covers  Monolog\Handler\ZendMonitorHandler::write
     */
    public function testWrite()
    {
        $record = $this->getRecord();
        $formatterResult = [
            'message' => $record->message,
        ];

        $zendMonitor = $this->getMockBuilder('Monolog\Handler\ZendMonitorHandler')
            ->onlyMethods(['writeZendMonitorCustomEvent', 'getDefaultFormatter'])
            ->getMock();

        $formatterMock = $this->getMockBuilder('Monolog\Formatter\NormalizerFormatter')
            ->disableOriginalConstructor()
            ->getMock();

        $formatterMock->expects($this->once())
            ->method('format')
            ->will($this->returnValue($formatterResult));

        $zendMonitor->expects($this->once())
            ->method('getDefaultFormatter')
            ->will($this->returnValue($formatterMock));

        $zendMonitor->expects($this->once())
            ->method('writeZendMonitorCustomEvent')
            ->with(
                $record->level->getName(),
                $record->message,
                $formatterResult,
                \ZEND_MONITOR_EVENT_SEVERITY_WARNING
            );

        $zendMonitor->handle($record);
    }

    /**
     * @covers Monolog\Handler\ZendMonitorHandler::getDefaultFormatter
     */
    public function testGetDefaultFormatterReturnsNormalizerFormatter()
    {
        $zendMonitor = new ZendMonitorHandler();
        $this->assertInstanceOf('Monolog\Formatter\NormalizerFormatter', $zendMonitor->getDefaultFormatter());
    }
}
