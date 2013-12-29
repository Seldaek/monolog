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

/**
 * @covers Monolog\Handler\TestHandler
 */
class TestHandlerTest extends TestCase
{
    /**
     * @dataProvider methodProvider
     */
    public function testHandler($method, $level)
    {
        $handler = new TestHandler;
        $record  = $this->getRecord($level, 'test' . $method);
        self::assertFalse($handler->{'has' . $method}($record));
        self::assertFalse($handler->{'has' . $method . 'Records'}());
        $handler->handle($record);

        self::assertFalse($handler->{'has' . $method}('bar'));
        self::assertTrue($handler->{'has' . $method}($record));
        self::assertTrue($handler->{'has' . $method}('test' . $method));
        self::assertTrue($handler->{'has' . $method . 'Records'}());

        $records = $handler->getRecords();
        unset($records[0]['formatted']);
        self::assertEquals(array($record), $records);
    }

    public function methodProvider()
    {
        return array(
            array('Emergency', Logger::EMERGENCY),
            array('Alert', Logger::ALERT),
            array('Critical', Logger::CRITICAL),
            array('Error', Logger::ERROR),
            array('Warning', Logger::WARNING),
            array('Info', Logger::INFO),
            array('Notice', Logger::NOTICE),
            array('Debug', Logger::DEBUG),
        );
    }
}
