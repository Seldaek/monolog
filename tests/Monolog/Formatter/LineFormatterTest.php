<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;

class LineFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testDefFormatWithString()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $record = $formatter->format(array(
            'level_name' => 'WARNING',
            'channel' => 'log',
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array(),
        ));
        $this->assertEquals('['.date('Y-m-d').'] log.WARNING: foo '."\n", $record['message']);
    }

    public function testDefFormatWithArray()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $record = $formatter->format(array(
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'datetime' => new \DateTime,
            'extra' => array(),
            'message' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: message(foo: bar, baz: qux) '."\n", $record['message']);
    }

    public function testDefFormatExtras()
    {
        $formatter = new LineFormatter(null, 'Y-m-d');
        $record = $formatter->format(array(
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'datetime' => new \DateTime,
            'extra' => array('ip' => '127.0.0.1'),
            'message' => 'log',
        ));
        $this->assertEquals('['.date('Y-m-d').'] meh.ERROR: log extra(ip: 127.0.0.1)'."\n", $record['message']);
    }
}
