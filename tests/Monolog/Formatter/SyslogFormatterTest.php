<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SyslogFormatterTest extends TestCase
{
    /**
     * @param mixed[] $context
     * @param mixed[] $extra
     */
    #[DataProvider('formatDataProvider')]
    public function testFormat(
        string $expected,
        DateTimeImmutable $dateTime,
        string $channel,
        Level $level,
        string $message,
        string $appName = null,
        array $context = [],
        array $extra = []
    ): void {
        if ($appName !== null) {
            $formatter = new SyslogFormatter($appName);
        } else {
            $formatter = new SyslogFormatter();
        }

        $record = new LogRecord(
            datetime: $dateTime,
            channel: $channel,
            level: $level,
            message: $message,
            context: $context,
            extra: $extra
        );

        $message = $formatter->format($record);

        $this->assertEquals($expected, $message);
    }

    /**
     * @return mixed[]
     */
    public static function formatDataProvider(): array
    {
        return [
            'error' => [
                'expected' => "<11>1 1970-01-01T00:00:00.000000+00:00 " . gethostname() . " - " . getmypid() ." meh - ERROR: log  \n",
                'dateTime' => new DateTimeImmutable("@0"),
                'channel' => 'meh',
                'level' => Level::Error,
                'message' => 'log',
            ],
            'info' => [
                'expected' => "<11>1 1970-01-01T00:00:00.000000+00:00 " . gethostname() . " - " . getmypid() ." meh - ERROR: log  \n",
                'dateTime' => new DateTimeImmutable("@0"),
                'channel' => 'meh',
                'level' => Level::Error,
                'message' => 'log',
            ],
            'with app name' => [
                'expected' => "<11>1 1970-01-01T00:00:00.000000+00:00 " . gethostname() . " my-app " . getmypid() ." meh - ERROR: log  \n",
                'dateTime' => new DateTimeImmutable("@0"),
                'channel' => 'meh',
                'level' => Level::Error,
                'message' => 'log',
                'appName' => 'my-app',
            ],
            'with context' => [
                'expected' => "<11>1 1970-01-01T00:00:00.000000+00:00 " . gethostname() . " - " . getmypid() ." meh - ERROR: log {\"additional-context\":\"test\"} \n",
                'dateTime' => new DateTimeImmutable("@0"),
                'channel' => 'meh',
                'level' => Level::Error,
                'message' => 'log',
                'appName' => null,
                'context' => ['additional-context' => 'test'],
            ],
            'with extra' => [
                'expected' => "<11>1 1970-01-01T00:00:00.000000+00:00 " . gethostname() . " - " . getmypid() ." meh - ERROR: log  {\"userId\":1}\n",
                'dateTime' => new DateTimeImmutable("@0"),
                'channel' => 'meh',
                'level' => Level::Error,
                'message' => 'log',
                'appName' => null,
                'context' => [],
                'extra' => ['userId' => 1],
            ],
        ];
    }
}
