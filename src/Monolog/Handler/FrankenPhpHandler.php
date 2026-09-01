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

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Logs records using FrankenPHP's frankenphp_log() function.
 *
 * This handler requires a NormalizerFormatter to function and expects an array in $record->formatted
 *
 * @see https://frankenphp.dev/docs/logging/#frankenphp_log
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class FrankenPhpHandler extends AbstractProcessingHandler
{
    /**
     * @throws MissingExtensionException If frankenphp_log() is not available
     */
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if (!\function_exists('frankenphp_log')) {
            throw new MissingExtensionException(
                'You must run this handler under FrankenPHP, the frankenphp_log() function is not available'
            );
        }

        parent::__construct($level, $bubble);
    }

    /**
     * Translates Monolog log levels to FrankenPHP's slog-based levels.
     *
     * slog treats levels as arbitrary ints and recommends a gap of 4 between named levels to leave
     * room for intermediate ones, e.g. Google Cloud Logging's Notice between Info and Warn.
     *
     * FrankenPHP feeds these through zapslog, which buckets everything >= Error into "error", so
     * above Error only the level_name context key tells the Monolog levels apart.
     *
     * @see https://pkg.go.dev/log/slog#hdr-Levels
     */
    protected function toFrankenPhpLevel(Level $level): int
    {
        return match ($level) {
            Level::Debug => \FRANKENPHP_LOG_LEVEL_DEBUG,
            Level::Info => \FRANKENPHP_LOG_LEVEL_INFO,
            Level::Notice => 2,
            Level::Warning => \FRANKENPHP_LOG_LEVEL_WARN,
            Level::Error => \FRANKENPHP_LOG_LEVEL_ERROR,
            Level::Critical => 12,
            Level::Alert => 16,
            Level::Emergency => 20,
        };
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $this->writeFrankenPhpLog($record->message, $this->toFrankenPhpLevel($record->level), $record->formatted);
    }

    /**
     * @param array<mixed> $context Displayed as structured attributes alongside the message
     */
    protected function writeFrankenPhpLog(string $message, int $level, array $context): void
    {
        // frankenphp_log() merges $context into the same JSON object it already fills with "msg",
        // "level" and "ts", so drop Monolog's duplicates of those three. level_name still carries
        // the Monolog level name, which slog's own severity cannot express.
        unset($context['message'], $context['level'], $context['datetime']);

        \frankenphp_log($message, $level, $context);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultFormatter(): FormatterInterface
    {
        return new NormalizerFormatter();
    }
}
