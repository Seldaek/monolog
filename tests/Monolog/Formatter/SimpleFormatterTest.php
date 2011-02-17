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

class SimpleFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testDefFormatWithString()
    {
        $formatter = new SimpleFormatter(null, 'Y-m-d');
        $message = $formatter->format('log', Logger::WARNING, 'foo');
        $this->assertEquals('['.date('Y-m-d').'] log.WARNING: foo'."\n", $message);
    }

    public function testDefFormatWithArray()
    {
        $formatter = new SimpleFormatter(null, 'Y-m-d');
        $message = $formatter->format('xx', Logger::ERROR, array(
            'log' => 'log',
            'level' => 'WARNING',
            'message' => 'foo'
        ));
        $this->assertEquals('['.date('Y-m-d').'] log.WARNING: foo'."\n", $message);
    }
}
