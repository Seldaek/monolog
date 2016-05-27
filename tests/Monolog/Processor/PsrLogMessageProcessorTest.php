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

    public function getPairs()
    {
        return [
            ['foo',    'foo'],
            ['3',      '3'],
            [3,        '3'],
            [null,     ''],
            [true,     '1'],
            [false,    ''],
            [new \stdClass, '[object stdClass]'],
            [[], '[array]'],
        ];
    }
}
