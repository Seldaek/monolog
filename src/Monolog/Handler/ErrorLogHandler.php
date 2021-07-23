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

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Monolog\Utils;

/**
 * Stores to PHP error_log() handler.
 *
 * @author Elan Ruusamäe <glen@delfi.ee>
 */
class ErrorLogHandler extends AbstractProcessingHandler
{
    public const OPERATING_SYSTEM = 0;
    public const SAPI = 4;

    /** @var int */
    protected $messageType;
    /** @var bool */
    protected $expandNewlines;

    /**
     * @param int  $messageType    Says where the error should go.
     * @param bool $expandNewlines If set to true, newlines in the message will be expanded to be take multiple log entries
     */
    public function __construct(int $messageType = self::OPERATING_SYSTEM, $level = Logger::DEBUG, bool $bubble = true, bool $expandNewlines = false)
    {
        parent::__construct($level, $bubble);

        if (false === in_array($messageType, self::getAvailableTypes(), true)) {
            $message = sprintf('The given message type "%s" is not supported', print_r($messageType, true));

            throw new \InvalidArgumentException($message);
        }

        $this->messageType = $messageType;
        $this->expandNewlines = $expandNewlines;
    }

    /**
     * @return int[] With all available types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::OPERATING_SYSTEM,
            self::SAPI,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %context% %extra%');
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record): void
    {
        if (!$this->expandNewlines) {
            error_log((string) $record['formatted'], $this->messageType);

            return;
        }

        $lines = preg_split('{[\r\n]+}', (string) $record['formatted']);
        if ($lines === false) {
            $pcreErrorCode = preg_last_error();
            throw new \RuntimeException('Failed to preg_split formatted string: ' . $pcreErrorCode . ' / '. Utils::pcreLastErrorMessage($pcreErrorCode));
        }
        foreach ($lines as $line) {
            error_log($line, $this->messageType);
        }
    }
}
