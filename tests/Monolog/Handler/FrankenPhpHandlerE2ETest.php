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

use PHPUnit\Framework\Attributes\Group;

/**
 * Requires a FrankenPHP server serving tests/e2e/frankenphp/public on 127.0.0.1:8099, e.g.:
 *
 * docker run -d --rm -v "$PWD":/app/source:ro -v "$PWD/tests/e2e/frankenphp/public":/app/public \
 *     -e SERVER_NAME=":8099" -p 8099:8099 dunglas/frankenphp:latest
 */
#[Group('e2e')]
class FrankenPhpHandlerE2ETest extends \Monolog\Test\MonologTestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 8099;

    protected function setUp(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $connection = @fsockopen(self::HOST, self::PORT, $errno, $errstr, 1);
            if (false !== $connection) {
                fclose($connection);

                return;
            }
            sleep(1);
        }

        $this->markTestSkipped(sprintf('FrankenPHP is not running on %s:%d', self::HOST, self::PORT));
    }

    public function testFrankenPhpLogHandlerWritesStructuredLogs()
    {
        $response = file_get_contents(sprintf('http://%s:%d/', self::HOST, self::PORT));
        $this->assertSame('OK', $response);

        $containerId = trim((string) shell_exec('docker ps -q --filter ancestor=dunglas/frankenphp:latest'));
        $this->assertNotSame('', $containerId, 'Could not find a running dunglas/frankenphp container');

        $logs = (string) shell_exec('docker logs ' . escapeshellarg($containerId) . ' 2>&1');

        $this->assertStringContainsString('"msg":"Hello from Monolog via FrankenPHP"', $logs);
        $this->assertStringContainsString('"level":"warn"', $logs);
        $this->assertStringContainsString('"msg":"Custom FrankenPHP level"', $logs);
    }
}
