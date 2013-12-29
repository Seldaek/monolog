<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\TestCase;

class WebProcessorTest extends TestCase
{
    public function testProcessor()
    {
        $server = array(
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'HTTP_REFERER'   => 'D',
            'SERVER_NAME'    => 'F',
            'UNIQUE_ID'      => 'G',
        );

        $processor = new WebProcessor($server);
        $record    = $processor($this->getRecord());
        self::assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        self::assertEquals($server['REMOTE_ADDR'], $record['extra']['ip']);
        self::assertEquals($server['REQUEST_METHOD'], $record['extra']['http_method']);
        self::assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);
        self::assertEquals($server['SERVER_NAME'], $record['extra']['server']);
        self::assertEquals($server['UNIQUE_ID'], $record['extra']['unique_id']);
    }

    public function testProcessorDoNothingIfNoRequestUri()
    {
        $server    = array(
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
        );
        $processor = new WebProcessor($server);
        $record    = $processor($this->getRecord());
        self::assertEmpty($record['extra']);
    }

    public function testProcessorReturnNullIfNoHttpReferer()
    {
        $server    = array(
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'F',
        );
        $processor = new WebProcessor($server);
        $record    = $processor($this->getRecord());
        self::assertNull($record['extra']['referrer']);
    }

    public function testProcessorDoesNotAddUniqueIdIfNotPresent()
    {
        $server    = array(
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'F',
        );
        $processor = new WebProcessor($server);
        $record    = $processor($this->getRecord());
        self::assertFalse(isset($record['extra']['unique_id']));
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidData()
    {
        new WebProcessor(new \stdClass);
    }
}
