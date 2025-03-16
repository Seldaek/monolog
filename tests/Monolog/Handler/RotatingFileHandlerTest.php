<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers Monolog\Handler\RotatingFileHandler
 */
class RotatingFileHandlerTest extends \Monolog\Test\MonologTestCase
{
    private array|null $lastError = null;

    public function setUp(): void
    {
        $dir = __DIR__.'/Fixtures';
        chmod($dir, 0777);
        if (!is_writable($dir)) {
            $this->markTestSkipped($dir.' must be writable to test the RotatingFileHandler.');
        }
        $this->lastError = null;
        set_error_handler(function ($code, $message) {
            $this->lastError = [
                'code' => $code,
                'message' => $message,
            ];

            return true;
        });
    }

    public function tearDown(): void
    {
        parent::tearDown();

        foreach (glob(__DIR__.'/Fixtures/*.rot') as $file) {
            unlink($file);
        }

        if ('testRotationWithFolderByDate' === $this->name()) {
            foreach (glob(__DIR__.'/Fixtures/[0-9]*') as $folder) {
                $this->rrmdir($folder);
            }
        }

        restore_error_handler();

        unset($this->lastError);
    }

    private function rrmdir($directory)
    {
        if (! is_dir($directory)) {
            throw new InvalidArgumentException("$directory must be a directory");
        }

        if (substr($directory, \strlen($directory) - 1, 1) !== '/') {
            $directory .= '/';
        }

        foreach (glob($directory . '*', GLOB_MARK) as $path) {
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }

    private function assertErrorWasTriggered($code, $message)
    {
        if (empty($this->lastError)) {
            $this->fail(
                \sprintf(
                    'Failed asserting that error with code `%d` and message `%s` was triggered',
                    $code,
                    $message
                )
            );
        }
        $this->assertEquals($code, $this->lastError['code'], \sprintf('Expected an error with code %d to be triggered, got `%s` instead', $code, $this->lastError['code']));
        $this->assertEquals($message, $this->lastError['message'], \sprintf('Expected an error with message `%d` to be triggered, got `%s` instead', $message, $this->lastError['message']));
    }

    public function testRotationCreatesNewFile()
    {
        touch(__DIR__.'/Fixtures/foo-'.date('Y-m-d', time() - 86400).'.rot');

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot');
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord());

        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        $this->assertTrue(file_exists($log));
        $this->assertEquals('test', file_get_contents($log));
    }

    #[DataProvider('rotationTests')]
    public function testRotation($createFile, $dateFormat, $timeCallback)
    {
        touch($old1 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-1)).'.rot');
        touch($old2 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-2)).'.rot');
        touch($old3 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-3)).'.rot');
        touch($old4 = __DIR__.'/Fixtures/foo-'.date($dateFormat, $timeCallback(-4)).'.rot');

        $log = __DIR__.'/Fixtures/foo-'.date($dateFormat).'.rot';

        if ($createFile) {
            touch($log);
        }

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->setFilenameFormat('{filename}-{date}', $dateFormat);
        $handler->handle($this->getRecord());

        $handler->close();

        $this->assertTrue(file_exists($log));
        $this->assertTrue(file_exists($old1));
        $this->assertEquals($createFile, file_exists($old2));
        $this->assertEquals($createFile, file_exists($old3));
        $this->assertEquals($createFile, file_exists($old4));
        $this->assertEquals('test', file_get_contents($log));
    }

    public static function rotationTests()
    {
        $now = time();
        $dayCallback = function ($ago) use ($now) {
            return $now + 86400 * $ago;
        };
        $monthCallback = function ($ago) {
            return gmmktime(0, 0, 0, (int) (date('n') + $ago), 1, (int) date('Y'));
        };
        $yearCallback = function ($ago) {
            return gmmktime(0, 0, 0, 1, 1, (int) (date('Y') + $ago));
        };

        return [
            'Rotation is triggered when the file of the current day is not present'
                => [true, RotatingFileHandler::FILE_PER_DAY, $dayCallback],
            'Rotation is not triggered when the file of the current day is already present'
                => [false, RotatingFileHandler::FILE_PER_DAY, $dayCallback],

            'Rotation is triggered when the file of the current month is not present'
                => [true, RotatingFileHandler::FILE_PER_MONTH, $monthCallback],
            'Rotation is not triggered when the file of the current month is already present'
                => [false, RotatingFileHandler::FILE_PER_MONTH, $monthCallback],

            'Rotation is triggered when the file of the current year is not present'
                => [true, RotatingFileHandler::FILE_PER_YEAR, $yearCallback],
            'Rotation is not triggered when the file of the current year is already present'
                => [false, RotatingFileHandler::FILE_PER_YEAR, $yearCallback],
        ];
    }

    private function createDeep($file)
    {
        mkdir(\dirname($file), 0777, true);
        touch($file);

        return $file;
    }

    #[DataProvider('rotationWithFolderByDateTests')]
    public function testRotationWithFolderByDate($createFile, $dateFormat, $timeCallback)
    {
        $old1 = $this->createDeep(__DIR__.'/Fixtures/'.date($dateFormat, $timeCallback(-1)).'/foo.rot');
        $old2 = $this->createDeep(__DIR__.'/Fixtures/'.date($dateFormat, $timeCallback(-2)).'/foo.rot');
        $old3 = $this->createDeep(__DIR__.'/Fixtures/'.date($dateFormat, $timeCallback(-3)).'/foo.rot');
        $old4 = $this->createDeep(__DIR__.'/Fixtures/'.date($dateFormat, $timeCallback(-4)).'/foo.rot');

        $log = __DIR__.'/Fixtures/'.date($dateFormat).'/foo.rot';

        if ($createFile) {
            $this->createDeep($log);
        }

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->setFilenameFormat('{date}/{filename}', $dateFormat);
        $handler->handle($this->getRecord());

        $handler->close();

        $this->assertTrue(file_exists($log));
        $this->assertTrue(file_exists($old1));
        $this->assertEquals($createFile, file_exists($old2));
        $this->assertEquals($createFile, file_exists($old3));
        $this->assertEquals($createFile, file_exists($old4));
        $this->assertEquals('test', file_get_contents($log));
    }

    public static function rotationWithFolderByDateTests()
    {
        $now = time();
        $dayCallback = function ($ago) use ($now) {
            return $now + 86400 * $ago;
        };
        $monthCallback = function ($ago) {
            return gmmktime(0, 0, 0, (int) (date('n') + $ago), 1, (int) date('Y'));
        };
        $yearCallback = function ($ago) {
            return gmmktime(0, 0, 0, 1, 1, (int) (date('Y') + $ago));
        };

        return [
            'Rotation is triggered when the file of the current day is not present'
                => [true, 'Y/m/d', $dayCallback],
            'Rotation is not triggered when the file of the current day is already present'
                => [false, 'Y/m/d', $dayCallback],

            'Rotation is triggered when the file of the current month is not present'
                => [true, 'Y/m', $monthCallback],
            'Rotation is not triggered when the file of the current month is already present'
                => [false, 'Y/m', $monthCallback],

            'Rotation is triggered when the file of the current year is not present'
                => [true, 'Y', $yearCallback],
            'Rotation is not triggered when the file of the current year is already present'
                => [false, 'Y', $yearCallback],
        ];
    }

    #[DataProvider('dateFormatProvider')]
    public function testAllowOnlyFixedDefinedDateFormats($dateFormat, $valid)
    {
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('~^Invalid date format~');
        }
        $handler->setFilenameFormat('{filename}-{date}', $dateFormat);
        $this->assertTrue(true);
    }

    public static function dateFormatProvider()
    {
        return [
            [RotatingFileHandler::FILE_PER_DAY, true],
            [RotatingFileHandler::FILE_PER_MONTH, true],
            [RotatingFileHandler::FILE_PER_YEAR, true],
            ['Y/m/d', true],
            ['Y.m.d', true],
            ['Y_m_d', true],
            ['Ymd', true],
            ['Ym/d', true],
            ['Y/m', true],
            ['Ym', true],
            ['Y.m', true],
            ['Y_m', true],
            ['Y/md', true],
            ['', false],
            ['m-d-Y', false],
            ['Y-m-d-h-i', false],
            ['Y-', false],
            ['Y-m-', false],
            ['Y--', false],
            ['m-d', false],
            ['Y-d', false],
        ];
    }

    #[DataProvider('filenameFormatProvider')]
    public function testDisallowFilenameFormatsWithoutDate($filenameFormat, $valid)
    {
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('~^Invalid filename format~');
        }

        $handler->setFilenameFormat($filenameFormat, RotatingFileHandler::FILE_PER_DAY);
    }

    public static function filenameFormatProvider()
    {
        return [
            ['{filename}', false],
            ['{filename}-{date}', true],
            ['{date}', true],
            ['foobar-{date}', true],
            ['foo-{date}-bar', true],
            ['{date}-foobar', true],
            ['{date}/{filename}', true],
            ['foobar', false],
        ];
    }

    #[DataProvider('rotationWhenSimilarFilesExistTests')]
    public function testRotationWhenSimilarFileNamesExist($dateFormat)
    {
        touch($old1 = __DIR__.'/Fixtures/foo-foo-'.date($dateFormat).'.rot');
        touch($old2 = __DIR__.'/Fixtures/foo-bar-'.date($dateFormat).'.rot');

        $log = __DIR__.'/Fixtures/foo-'.date($dateFormat).'.rot';

        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot', 2);
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->setFilenameFormat('{filename}-{date}', $dateFormat);
        $handler->handle($this->getRecord());
        $handler->close();

        $this->assertTrue(file_exists($log));
    }

    public static function rotationWhenSimilarFilesExistTests()
    {
        return [
            'Rotation is triggered when the file of the current day is not present but similar exists'
                => [RotatingFileHandler::FILE_PER_DAY],

            'Rotation is triggered when the file of the current month is not present but similar exists'
                => [RotatingFileHandler::FILE_PER_MONTH],

            'Rotation is triggered when the file of the current year is not present but similar exists'
                => [RotatingFileHandler::FILE_PER_YEAR],
        ];
    }

    public function testReuseCurrentFile()
    {
        $log = __DIR__.'/Fixtures/foo-'.date('Y-m-d').'.rot';
        file_put_contents($log, "foo");
        $handler = new RotatingFileHandler(__DIR__.'/Fixtures/foo.rot');
        $handler->setFormatter($this->getIdentityFormatter());
        $handler->handle($this->getRecord());
        $this->assertEquals('footest', file_get_contents($log));
    }
}
