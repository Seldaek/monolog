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
    /**
     * @covers Monolog\Formatter\WildfireFormatter::format
     */
    public function testDefaultFormatIsLineFormatterWithoutNewLine()
    {
        $wildfire = new WildfireFormatter();
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => array('from' => 'logger'),
            'datetime' => new \DateTime("@0"),
            'extra' => array('ip' => '127.0.0.1'),
            'message' => 'log',
        );

        $message = $wildfire->format($record);

        $this->assertEquals(
            '125|[{"Type":"ERROR","File":"","Line":"","Label":"meh"},'
                .'{"message":"log","context":{"from":"logger"},"extra":{"ip":"127.0.0.1"}}]|',
            $message
        );
    }
}
