<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\Test\TestCase;

class WebProcessorTest extends TestCase
{
    public function testProcessor()
    {
        $server = [
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'HTTP_REFERER'   => 'D',
            'SERVER_NAME'    => 'F',
            'UNIQUE_ID'      => 'G',
        ];

        $processor = new WebProcessor($server);
        $record = $processor($this->getRecord());
        $this->assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        $this->assertEquals($server['REMOTE_ADDR'], $record['extra']['ip']);
        $this->assertEquals($server['REQUEST_METHOD'], $record['extra']['http_method']);
        $this->assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);
        $this->assertEquals($server['SERVER_NAME'], $record['extra']['server']);
        $this->assertEquals($server['UNIQUE_ID'], $record['extra']['unique_id']);
    }

    public function testProcessorDoNothingIfNoRequestUri()
    {
        $server = [
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
        ];
        $processor = new WebProcessor($server);
        $record = $processor($this->getRecord());
        $this->assertEmpty($record['extra']);
    }

    public function testProcessorReturnNullIfNoHttpReferer()
    {
        $server = [
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'F',
        ];
        $processor = new WebProcessor($server);
        $record = $processor($this->getRecord());
        $this->assertNull($record['extra']['referrer']);
    }

    public function testProcessorDoesNotAddUniqueIdIfNotPresent()
    {
        $server = [
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'F',
        ];
        $processor = new WebProcessor($server);
        $record = $processor($this->getRecord());
        $this->assertFalse(isset($record['extra']['unique_id']));
    }

    public function testProcessorAddsOnlyRequestedExtraFields()
    {
        $server = [
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'F',
        ];

        $processor = new WebProcessor($server, ['url', 'http_method']);
        $record = $processor($this->getRecord());

        $this->assertSame(['url' => 'A', 'http_method' => 'C'], $record['extra']);
    }

    public function testProcessorAddsOnlyRequestedExtraFieldsIncludingOptionalFields()
    {
        $server = array(
            'REQUEST_URI'  => 'A',
            'UNIQUE_ID'    => 'X',
        );

        $processor = new WebProcessor($server, array('url'));
        $record = $processor($this->getRecord());

        $this->assertSame(array('url' => 'A'), $record['extra']);
    }

    public function testProcessorConfiguringOfExtraFields()
    {
        $server = [
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'F',
        ];

        $processor = new WebProcessor($server, ['url' => 'REMOTE_ADDR']);
        $record = $processor($this->getRecord());

        $this->assertSame(['url' => 'B'], $record['extra']);
    }

    public function testInvalidData()
    {
        $this->expectException(\UnexpectedValueException::class);

        new WebProcessor(new \stdClass);
    }
}
