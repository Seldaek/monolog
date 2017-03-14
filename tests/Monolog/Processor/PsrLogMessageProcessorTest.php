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

class PsrLogMessageProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getPairs
     */
    public function testReplacement($val, $expected)
    {
        $proc = new PsrLogMessageProcessor;

        $message = $proc([
            'message' => '{foo}',
            'context' => ['foo' => $val],
        ]);
        $this->assertEquals($expected, $message['message']);
    }

    public function testCustomDateFormat()
    {
        $format = "Y-m-d";
        $date = new \DateTime();

        $proc = new PsrLogMessageProcessor($format);

        $message = $proc([
            'message' => '{foo}',
            'context' => ['foo' => $date],
        ]);
        $this->assertEquals($date->format($format), $message['message']);
    }

    public function getPairs()
    {
        $date = new \DateTime();

        return [
            ['foo',    'foo'],
            ['3',      '3'],
            [3,        '3'],
            [null,     ''],
            [true,     '1'],
            [false,    ''],
            [$date, $date->format(PsrLogMessageProcessor::SIMPLE_DATE)],
            [new \stdClass, '[object stdClass]'],
            [[], '[array]'],
        ];
    }
}
