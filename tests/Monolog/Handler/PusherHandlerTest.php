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
use Monolog\Handler\PusherMock;

/**
 * @covers Monolog\Handler\PusherHandler
 */
class PusherHandlerTest extends TestCase
{
    public function testHandle()
    {
        $pusher = $this->getPusher();

        $handler = new PusherHandler($pusher, 'info_log');

        $record = $this->getRecord(Logger::INFO, 'This message is pushed to pusher.com', array('data' => new \stdClass, 'foo' => 34));

        $this->assertEquals('pusher_com_channel_name', $record['channel']);// test channel is named appropriately
        $this->assertEquals('info_log', $handler->pusher_event);// test pusher event is named appropriately
        $this->assertTrue($pusher->trigger($record['channel'], $handler->pusher_event, $record));
    }

    /**
     * {@inheritDoc}
     */
    protected function getRecord($level = Logger::WARNING, $message = 'test', $context = array())
    {
        return array(
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'pusher_com_channel_name',
            'datetime' => \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true))),
            'extra' => array(),
        );
    }

    protected function getPusher()
    {
        return new PusherMock('pusher_key', 'pusher_secret', 12345);
    }
}
