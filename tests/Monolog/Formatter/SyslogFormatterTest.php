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

use Monolog\Formatter\SyslogFormatter;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class SyslogFormatterTest extends TestCase
{
    public function testDefaultFormatter(): void
    {
        $formatter = new \Monolog\Formatter\SyslogFormatter();
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger', 'exception' => [
                'class' => '\Exception',
                'file'  => '/some/file/in/dir.php:56',
                'trace' => ['/some/file/1.php:23', '/some/file/2.php:3'],
            ]],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];
        
        $message = $formatter->format($record);
        
        $this->assertEquals('<11>1 1970-01-01T00:00:00.000000+00:00 '. gethostname() .' - '. getmypid() .' meh - log'."\n", $message);
    }

    public function testFormatterWithAppName(): void
    {
        $formatter = new \Monolog\Formatter\SyslogFormatter('my-app');
        $record = [
            'level' => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => ['from' => 'logger', 'exception' => [
                'class' => '\Exception',
                'file'  => '/some/file/in/dir.php:56',
                'trace' => ['/some/file/1.php:23', '/some/file/2.php:3'],
            ]],
            'datetime' => new \DateTimeImmutable("@0"),
            'extra' => [],
            'message' => 'log',
        ];

        $message = $formatter->format($record);

        $this->assertEquals('<11>1 1970-01-01T00:00:00.000000+00:00 '. gethostname() .' my-app '. getmypid() .' meh - log'."\n", $message);
    }
}
