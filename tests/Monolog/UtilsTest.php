<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $expected
     * @param object $object
     * @dataProvider provideObjects
     */
    public function testGetClass($expected, $object)
    {
        $this->assertSame($expected, Utils::getClass($object));
    }

    public function provideObjects()
    {
        return [
            ['stdClass', new \stdClass()],
            ['class@anonymous', new class {}],
            ['stdClass@anonymous', new class extends \stdClass {}],
        ];
    }

    /**
     * @param string $expected
     * @param string $input
     * @dataProvider providePathsToCanonicalize
     */
    public function testCanonicalizePath($expected, $input)
    {
        $this->assertSame($expected, Utils::canonicalizePath($input));
    }

    public function providePathsToCanonicalize()
    {
        return array(
            array('/foo/bar', '/foo/bar'),
            array('file://'.getcwd().'/bla', 'file://bla'),
            array(getcwd().'/bla', 'bla'),
            array(getcwd().'/./bla', './bla'),
            array('file:///foo/bar', 'file:///foo/bar'),
            array('any://foo', 'any://foo'),
            array('\\\\network\path', '\\\\network\path'),
        );
    }

    /**
     * @param int    $code
     * @param string $msg
     * @dataProvider providesHandleJsonErrorFailure
     */
    public function testHandleJsonErrorFailure($code, $msg)
    {
        $this->expectException('RuntimeException', $msg);
        Utils::handleJsonError($code, 'faked');
    }

    public function providesHandleJsonErrorFailure()
    {
        return [
            'depth' => [JSON_ERROR_DEPTH, 'Maximum stack depth exceeded'],
            'state' => [JSON_ERROR_STATE_MISMATCH, 'Underflow or the modes mismatch'],
            'ctrl' => [JSON_ERROR_CTRL_CHAR, 'Unexpected control character found'],
            'default' => [-1, 'Unknown error'],
        ];
    }

    /**
     * @param mixed $in     Input
     * @param mixed $expect Expected output
     * @covers Monolog\Formatter\NormalizerFormatter::detectAndCleanUtf8
     * @dataProvider providesDetectAndCleanUtf8
     */
    public function testDetectAndCleanUtf8($in, $expect)
    {
        $reflMethod = new \ReflectionMethod(Utils::class, 'detectAndCleanUtf8');
        $reflMethod->setAccessible(true);
        $args = [&$in];
        $reflMethod->invokeArgs(null, $args);
        $this->assertSame($expect, $in);
    }

    public function providesDetectAndCleanUtf8()
    {
        $obj = new \stdClass;

        return [
            'null' => [null, null],
            'int' => [123, 123],
            'float' => [123.45, 123.45],
            'bool false' => [false, false],
            'bool true' => [true, true],
            'ascii string' => ['abcdef', 'abcdef'],
            'latin9 string' => ["\xB1\x31\xA4\xA6\xA8\xB4\xB8\xBC\xBD\xBE\xFF", '±1€ŠšŽžŒœŸÿ'],
            'unicode string' => ['¤¦¨´¸¼½¾€ŠšŽžŒœŸ', '¤¦¨´¸¼½¾€ŠšŽžŒœŸ'],
            'empty array' => [[], []],
            'array' => [['abcdef'], ['abcdef']],
            'object' => [$obj, $obj],
        ];
    }
}
