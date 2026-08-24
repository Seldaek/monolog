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
 * docker run -d --rm --name monolog-frankenphp-e2e -v "$PWD":/app/source:ro \
 *     -v "$PWD/tests/e2e/frankenphp/public":/app/public \
 *     -e SERVER_NAME=":8099" -p 8099:8099 dunglas/frankenphp:latest
 *
 * Skips when no server is reachable, unless MONOLOG_FRANKENPHP_E2E is set, in which case it fails.
 * MONOLOG_FRANKENPHP_CONTAINER overrides the container name the logs are read from.
 */
#[Group('e2e')]
class FrankenPhpHandlerE2ETest extends \Monolog\Test\MonologTestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 8099;
    private const DEFAULT_CONTAINER = 'monolog-frankenphp-e2e';

    public static function setUpBeforeClass(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $connection = @fsockopen(self::HOST, self::PORT, $errno, $errstr, 1);
            if (false !== $connection) {
                fclose($connection);

                return;
            }
            sleep(1);
        }

        $message = sprintf('FrankenPHP is not running on %s:%d', self::HOST, self::PORT);

        // In CI the server is meant to be up, so a missing one is a failure. Skipping there would
        // let the whole e2e job report success without ever asserting anything.
        if (false !== getenv('MONOLOG_FRANKENPHP_E2E')) {
            self::fail($message);
        }

        self::markTestSkipped($message);
    }

    public function testFrankenPhpLogHandlerWritesStructuredLogs()
    {
        $response = file_get_contents(sprintf('http://%s:%d/', self::HOST, self::PORT));
        $this->assertSame('OK', $response);

        $entries = $this->readLogEntries();

        $this->assertLogEntry($entries, 'warn', 'Hello from Monolog via FrankenPHP');
        $this->assertLogEntry($entries, 'error', 'Custom FrankenPHP level');
    }

    /**
     * The Go side writes through zap and the Docker log driver, which is not synchronous with the
     * HTTP response, so poll rather than read once.
     *
     * @return array<array<string, mixed>>
     */
    private function readLogEntries(): array
    {
        $container = getenv('MONOLOG_FRANKENPHP_CONTAINER');
        if (false === $container || '' === $container) {
            $container = self::DEFAULT_CONTAINER;
        }

        $entries = [];
        for ($i = 0; $i < 10; $i++) {
            $logs = (string) shell_exec('docker logs '.escapeshellarg($container).' 2>&1');
            $entries = array_filter(array_map(
                static fn (string $line) => json_decode($line, true),
                explode("\n", trim($logs)),
            ));

            $messages = array_column($entries, 'msg');
            if (\in_array('Hello from Monolog via FrankenPHP', $messages, true) && \in_array('Custom FrankenPHP level', $messages, true)) {
                break;
            }

            usleep(200_000);
        }

        $this->assertNotSame([], $entries, sprintf('No log output from container "%s"', $container));

        return $entries;
    }

    /**
     * @param array<array<string, mixed>> $entries
     */
    private function assertLogEntry(array $entries, string $level, string $message): void
    {
        foreach ($entries as $entry) {
            if (($entry['msg'] ?? null) === $message) {
                $this->assertSame($level, $entry['level'] ?? null, "Unexpected level for message \"$message\"");

                return;
            }
        }

        $this->fail("No log entry found with message \"$message\"");
    }
}
