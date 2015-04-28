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

/**
 * @covers Monolog\Handler\FluentHandler
 */
class FluentHandlerTest extends TestCase
{
    public function testWrite()
    {
        if (!class_exists('Fluent\Logger\FluentLogger')) {
            $this->markTestSkipped("Please install fluent/logger to use FluentHandler");
        }

        $fluentLogger = $this->getMockBuilder('Fluent\Logger\FluentLogger')
            ->disableOriginalConstructor()
            ->getMock();

        $record = [
            'channel' => 'debugchannel',
            'message' => 'test',
            'context' => array('foo' => 'bar'),
            'level'   => Logger::DEBUG
        ];

        $expectedContext = $record['context'];
        $expectedContext['level'] = Logger::getLevelName($record['level']);
        $expectedContext['message'] = $record['message'];

        $fluentLogger->expects($this->once())->method('post')->with('debugchannel', $expectedContext);

        $handler = new FluentHandler($fluentLogger);
        $handler->write($record);
    }
}
