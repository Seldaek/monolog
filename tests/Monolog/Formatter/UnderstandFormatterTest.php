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

class UnderstandFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormatArrayWithDatetime()
    {
        $formatter = new UnderstandFormatter();

        $microtime = microtime(true);
        $miliseconds = round($microtime * 1000);
        $datetime = \DateTime::createFromFormat('U.u', sprintf('%.6F', $microtime));

        $array = array(
            'message' => 'test',
            'datetime' => $datetime
        );

        $resultJson = $formatter->format($array);
        $decoded = json_decode($resultJson, true);

        $this->assertEquals($miliseconds, $decoded['timestamp']);
        $this->assertEquals($array['message'], $decoded['message']);
    }
    
    public function testFormatArrayWithoutDatetime()
    {
        $formatter = new UnderstandFormatter();

        $array = array(
            'message' => 'test'
        );

        $resultJson = $formatter->format($array);
        $decoded = json_decode($resultJson, true);

        $this->assertEquals($array['message'], $decoded['message']);
    }
    
    public function testFormatBatch()
    {
        $formatter = new UnderstandFormatter();
        $microtime = microtime(true);
        $miliseconds = round($microtime * 1000);
        $datetime = \DateTime::createFromFormat('U.u', sprintf('%.6F', $microtime));

        $array = array(
            array(
                'message' => 'this is test',
                'datetime' => $datetime
            ),
            array(
                'message' => 'this is test 2',
                'datetime' => $datetime
            )
        );

        $resultJson = $formatter->formatBatch($array);
        $decoded = json_decode($resultJson, true);

        $this->assertEquals($miliseconds, $decoded[0]['timestamp']);
        $this->assertEquals($miliseconds, $decoded[1]['timestamp']);

        $this->assertEquals($array[0]['message'], $decoded[0]['message']);
        $this->assertEquals($array[1]['message'], $decoded[1]['message']);
    }
}