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

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\RedactingFormatter;
use Monolog\Formatter\WrappingFormatterInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    #[DataProvider('provideObjects')]
    public function testGetClass(string $expected, object $object)
    {
        $this->assertSame($expected, Utils::getClass($object));
    }

    public static function provideObjects()
    {
        return [
            ['stdClass', new \stdClass()],
            ['class@anonymous', new class {
            }],
            ['stdClass@anonymous', new class extends \stdClass {
            }],
        ];
    }

    #[DataProvider('providePathsToCanonicalize')]
    public function testCanonicalizePath(string $expected, string $input)
    {
        $this->assertSame($expected, Utils::canonicalizePath($input));
    }

    public static function providePathsToCanonicalize()
    {
        return [
            ['/foo/bar', '/foo/bar'],
            ['file://'.getcwd().'/bla', 'file://bla'],
            [getcwd().'/bla', 'bla'],
            [getcwd().'/./bla', './bla'],
            ['file:///foo/bar', 'file:///foo/bar'],
            ['any://foo', 'any://foo'],
            ['\\\\network\path', '\\\\network\path'],
        ];
    }

    #[DataProvider('providesHandleJsonErrorFailure')]
    public function testHandleJsonErrorFailure(int $code, string $msg)
    {
        $this->expectException('RuntimeException', $msg);
        Utils::handleJsonError($code, 'faked');
    }

    public static function providesHandleJsonErrorFailure()
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
     */
    #[DataProvider('providesDetectAndCleanUtf8')]
    public function testDetectAndCleanUtf8($in, $expect)
    {
        $reflMethod = new \ReflectionMethod(Utils::class, 'detectAndCleanUtf8');
        $args = [&$in];
        $reflMethod->invokeArgs(null, $args);
        $this->assertSame($expect, $in);
    }

    public static function providesDetectAndCleanUtf8()
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

    public static function provideIniValuesToConvertToBytes()
    {
        return [
            ['1', 1],
            ['2', 2],
            ['2.5', 2],
            ['2.9', 2],
            ['1B', false],
            ['1X', false],
            ['1K', 1024],
            ['1 K', 1024],
            ['   5 M  ', 5*1024*1024],
            ['1G', 1073741824],
            ['', false],
            [null, false],
            ['A', false],
            ['AA', false],
            ['B', false],
            ['BB', false],
            ['G', false],
            ['GG', false],
            ['-1', -1],
            ['-123', -123],
            ['-1A', -1],
            ['-1B', -1],
            ['-123G', -123],
            ['-B', false],
            ['-A', false],
            ['-', false],
            [true, false],
            [false, false],
        ];
    }

    #[DataProvider('provideIniValuesToConvertToBytes')]
    public function testExpandIniShorthandBytes(string|null|bool $input, int|false $expected)
    {
        $result = Utils::expandIniShorthandBytes($input);
        $this->assertEquals($expected, $result);
    }

    public function testUnwrapFormatterReturnsPlainFormattersAsIs()
    {
        $formatter = new LineFormatter();

        $this->assertSame($formatter, Utils::unwrapFormatter($formatter));
    }

    public function testUnwrapFormatterResolvesNestedWrappers()
    {
        $inner = new LineFormatter();

        $this->assertSame($inner, Utils::unwrapFormatter(new RedactingFormatter(new RedactingFormatter($inner))));
    }

    public function testUnwrapFormatterTerminatesOnASelfWrappingFormatter()
    {
        $formatter = new class implements WrappingFormatterInterface {
            public function getWrappedFormatter(): FormatterInterface
            {
                return $this;
            }

            public function format(LogRecord $record)
            {
                return '';
            }

            public function formatBatch(array $records)
            {
                return '';
            }
        };

        $this->assertSame($formatter, Utils::unwrapFormatter($formatter));
    }

    #[DataProvider('provideClassesWithSensitiveParameters')]
    public function testGetSensitiveParameterNames(array $expected, string $class)
    {
        $this->assertSame($expected, Utils::getSensitiveParameterNames($class));
        // twice, to make sure the cache returns the same thing
        $this->assertSame($expected, Utils::getSensitiveParameterNames($class));
    }

    public static function provideClassesWithSensitiveParameters(): array
    {
        return [
            'promoted, any visibility' => [['pub' => true, 'prot' => true, 'priv' => true], SensitiveParams::class],
            'non promoted' => [['notPromoted' => true], SensitiveNonPromotedParam::class],
            'unmarked parameters only' => [[], UnmarkedParams::class],
            'no constructor' => [[], \stdClass::class],
            'inherited constructor' => [['pub' => true, 'prot' => true, 'priv' => true], ExtendsSensitiveParams::class],
            'unknown class' => [[], 'Monolog\\ThisClassDoesNotExist'],
        ];
    }
}

class SensitiveParams
{
    public function __construct(
        public string $visible = 'a',
        #[\SensitiveParameter]
        public string $pub = 'b',
        #[\SensitiveParameter]
        protected string $prot = 'c',
        #[\SensitiveParameter]
        private string $priv = 'd',
    ) {
    }
}

class ExtendsSensitiveParams extends SensitiveParams
{
}

class SensitiveNonPromotedParam
{
    public function __construct(#[\SensitiveParameter] string $notPromoted = 'a')
    {
    }
}

class UnmarkedParams
{
    public function __construct(public string $visible = 'a')
    {
    }
}
