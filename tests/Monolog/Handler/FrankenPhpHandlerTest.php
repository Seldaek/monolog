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

use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;

class FrankenPhpHandlerTest extends \Monolog\Test\MonologTestCase
{
    /**
     * FrankenPHP only defines these under its own SAPI, so mirror slog's values to keep the level
     * mapping testable anywhere. The e2e test is what checks them against the real thing.
     *
     * Data providers run before setUpBeforeClass(), hence the explicit calls rather than a hook.
     */
    private static function defineLogLevelConstants(): void
    {
        \defined('FRANKENPHP_LOG_LEVEL_DEBUG') || \define('FRANKENPHP_LOG_LEVEL_DEBUG', -4);
        \defined('FRANKENPHP_LOG_LEVEL_INFO') || \define('FRANKENPHP_LOG_LEVEL_INFO', 0);
        \defined('FRANKENPHP_LOG_LEVEL_WARN') || \define('FRANKENPHP_LOG_LEVEL_WARN', 4);
        \defined('FRANKENPHP_LOG_LEVEL_ERROR') || \define('FRANKENPHP_LOG_LEVEL_ERROR', 8);
    }

    /**
     * @covers Monolog\Handler\FrankenPhpHandler::__construct
     */
    public function testConstructorThrowsWhenFrankenPhpIsNotAvailable()
    {
        if (\function_exists('frankenphp_log')) {
            $this->markTestSkipped('Running under FrankenPHP, the constructor does not throw');
        }

        $this->expectException(MissingExtensionException::class);

        new FrankenPhpHandler();
    }

    /**
     * @covers Monolog\Handler\FrankenPhpHandler::getDefaultFormatter
     */
    public function testGetDefaultFormatterReturnsNormalizerFormatter()
    {
        $handler = (new \ReflectionClass(FrankenPhpHandler::class))->newInstanceWithoutConstructor();

        $this->assertInstanceOf('Monolog\Formatter\NormalizerFormatter', $handler->getDefaultFormatter());
    }

    /**
     * @covers Monolog\Handler\FrankenPhpHandler::write
     */
    public function testWrite()
    {
        self::defineLogLevelConstants();

        $record = $this->getRecord(Level::Warning, 'test message', ['foo' => 'bar']);
        $formatterResult = [
            'message' => $record->message,
            'context' => ['foo' => 'bar'],
            'level' => $record->level->value,
            'level_name' => $record->level->getName(),
            'channel' => 'test',
            'datetime' => '2024-01-01T00:00:00.000000+00:00',
        ];

        $handler = $this->getMockBuilder(FrankenPhpHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['writeFrankenPhpLog', 'getDefaultFormatter'])
            ->getMock();

        $formatterMock = $this->getMockBuilder('Monolog\Formatter\NormalizerFormatter')
            ->disableOriginalConstructor()
            ->getMock();

        $formatterMock->expects($this->once())
            ->method('format')
            ->willReturn($formatterResult);

        $handler->expects($this->once())
            ->method('getDefaultFormatter')
            ->willReturn($formatterMock);

        $handler->expects($this->once())
            ->method('writeFrankenPhpLog')
            ->with('test message', \FRANKENPHP_LOG_LEVEL_WARN, $formatterResult);

        $handler->handle($record);
    }

    /**
     * @covers Monolog\Handler\FrankenPhpHandler::toFrankenPhpLevel
     */
    #[DataProvider('levelProvider')]
    public function testToFrankenPhpLevel(Level $level, int $expected)
    {
        $handler = (new \ReflectionClass(FrankenPhpHandler::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($handler, 'toFrankenPhpLevel');

        $this->assertSame($expected, $method->invoke($handler, $level));
    }

    /**
     * @return array<string, array{Level, int}>
     */
    public static function levelProvider(): array
    {
        self::defineLogLevelConstants();

        return [
            'debug' => [Level::Debug, \FRANKENPHP_LOG_LEVEL_DEBUG],
            'info' => [Level::Info, \FRANKENPHP_LOG_LEVEL_INFO],
            'notice' => [Level::Notice, 2],
            'warning' => [Level::Warning, \FRANKENPHP_LOG_LEVEL_WARN],
            'error' => [Level::Error, \FRANKENPHP_LOG_LEVEL_ERROR],
            'critical' => [Level::Critical, 12],
            'alert' => [Level::Alert, 16],
            'emergency' => [Level::Emergency, 20],
        ];
    }
}
