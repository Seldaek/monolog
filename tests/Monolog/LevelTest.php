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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LevelTest extends TestCase
{
    /**
     * @return array<string, array{int, Level}>
     */
    public static function rfc5424Provider(): array
    {
        return [
            'debug' => [7, Level::Debug],
            'info' => [6, Level::Info],
            'notice' => [5, Level::Notice],
            'warning' => [4, Level::Warning],
            'error' => [3, Level::Error],
            'critical' => [2, Level::Critical],
            'alert' => [1, Level::Alert],
            'emergency' => [0, Level::Emergency],
        ];
    }

    #[DataProvider('rfc5424Provider')]
    public function testRfc5424Mapping(int $rfc5424Level, Level $level): void
    {
        $this->assertSame($rfc5424Level, $level->toRFC5424Level());
        $this->assertSame($level, Level::fromRFC5424Level($rfc5424Level));
    }

    public function testFromRFC5424LevelRejectsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RFC 5424 level "8" is not defined');

        Level::fromRFC5424Level(8);
    }
}
