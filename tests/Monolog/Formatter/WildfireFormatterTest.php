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

class WildfireFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultFormatIsLineFormatterWithoutNewLine()
    {
        $wildfire = new WildfireFormatter();
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array(),
            'datetime' => new \DateTime,
            'extra' => array('ip' => '127.0.0.1'),
            'message' => 'log',
        );

        $message = $wildfire->format($record);

        $this->assertEquals(
            '84|[{"Type":"ERROR","File":"","Line":"","Label":"meh"},"log [] {\\"ip\\":\\"127.0.0.1\\"}"]|',
            $message
        );
    }
}
