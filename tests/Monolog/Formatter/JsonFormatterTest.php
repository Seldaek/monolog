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

class JsonFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormat()
    {
        $formatter = new JsonFormatter();
        $record = $formatter->format($msg = array(
            'level_name' => 'WARNING',
            'channel' => 'log',
            'message' => array('foo'),
            'datetime' => new \DateTime,
            'extra' => array(),
        ));
        $this->assertEquals(json_encode($msg), $record['message']);
    }
}
