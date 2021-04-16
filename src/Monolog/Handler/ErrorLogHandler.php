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

/**
 * Stores to PHP error_log() handler.
 *
 * @author Elan Ruusamäe <glen@delfi.ee>
 * @author Selçuk Çukur <hk@selcukcukur.com.tr>
 */
class ErrorLogHandler extends AbstractProcessingHandler
{
    public const OPERATING_SYSTEM = 0;
    public const LINE = 3;
    public const SAPI = 4;

    protected $messageType; 

    /**
     * @param int        $messageType    Says where the error should go.
     * @param int|string $level          The minimum logging level at which this handler will be triggered
     * @param bool       $bubble         Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(int $messageType = self::OPERATING_SYSTEM, $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        if (false === in_array($messageType, self::getAvailableTypes(), true)) {
            $message = sprintf('The given message type "%s" is not supported', print_r($messageType, true));

            throw new \InvalidArgumentException($message);
        }

        $this->messageType = $messageType; 
    }

    /**
     * @return array With all available types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::OPERATING_SYSTEM,
            self::LINE,
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
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        if($this->messageType === self::OPERATING_SYSTEM) {
            error_log((string) $record['formatted'], self::OPERATING_SYSTEM);
        }  elseif ($this->messageType === self::LINE) {
            error_log((string) $record['formatted'], self::LINE);
        } elseif ($this->messageType === self::SAPI) {
            error_log((string) $record['formatted'], self::SAPI);
        }
    }
}
