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
     * @dataProvider recordProvider
     */
    public function testDefaultFormatIsLineFormatterWithoutNewLine($record)
    {
        $wildfire = new WildfireFormatter();

        $record = $wildfire->format($record);

        $this->assertEquals(
            '70|[{"Type":"ERROR","File":"","Line":""},"meh: log extra(ip: 127.0.0.1)"]|',
            $record['message']
        );
    }

    public function recordProvider()
    {
        $record = array(
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'datetime' => new \DateTime,
            'extra' => array('ip' => '127.0.0.1'),
            'message' => 'log',
        );

        return array(
            array($record),
        );
    }

}
