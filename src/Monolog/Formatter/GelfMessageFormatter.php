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

use Monolog\Logger;
use Gelf\Message;
use Monolog\Utils;

/**
 * Serializes a log message to GELF
 * @see http://docs.graylog.org/en/latest/pages/gelf.html
 *
 * @author Matt Lehner <mlehner@gmail.com>
 *
 * @phpstan-import-type Level from \Monolog\Logger
 */
class GelfMessageFormatter extends NormalizerFormatter
{
    protected const DEFAULT_MAX_LENGTH = 32766;

    /**
     * @var string the name of the system for the Gelf log message
     */
    protected $systemName;

    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contextPrefix;

    /**
     * @var int max length per field
     */
    protected $maxLength;

    /**
     * @var int
     */
    private $gelfVersion = 2;

    /**
     * Translates Monolog log levels to Graylog2 log priorities.
     *
     * @var array<int, int>
     *
     * @phpstan-var array<Level, int>
     */
    private $logLevels = [
        Logger::DEBUG     => 7,
        Logger::INFO      => 6,
        Logger::NOTICE    => 5,
        Logger::WARNING   => 4,
        Logger::ERROR     => 3,
        Logger::CRITICAL  => 2,
        Logger::ALERT     => 1,
        Logger::EMERGENCY => 0,
    ];

    public function __construct(?string $systemName = null, ?string $extraPrefix = null, string $contextPrefix = 'ctxt_', ?int $maxLength = null)
    {
        if (!class_exists(Message::class)) {
            throw new \RuntimeException('Composer package graylog2/gelf-php is required to use Monolog\'s GelfMessageFormatter');
        }

        parent::__construct('U.u');

        $this->systemName = (is_null($systemName) || $systemName === '') ? (string) gethostname() : $systemName;

        $this->extraPrefix = is_null($extraPrefix) ? '' : $extraPrefix;
        $this->contextPrefix = $contextPrefix;
        $this->maxLength = is_null($maxLength) ? self::DEFAULT_MAX_LENGTH : $maxLength;

        if (method_exists(Message::class, 'setFacility')) {
            $this->gelfVersion = 1;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function format(array $record): Message
    {
        $context = $extra = [];
        if (isset($record['context'])) {
            /** @var mixed[] $context */
            $context = parent::normalize($record['context']);
        }
        if (isset($record['extra'])) {
            /** @var mixed[] $extra */
            $extra = parent::normalize($record['extra']);
        }

        if (!isset($record['datetime'], $record['message'], $record['level'])) {
            throw new \InvalidArgumentException('The record should at least contain datetime, message and level keys, '.var_export($record, true).' given');
        }

        $message = new Message();
        $message
            ->setTimestamp($record['datetime'])
            ->setShortMessage((string) $record['message'])
            ->setHost($this->systemName)
            ->setLevel($this->logLevels[$record['level']]);

        // message length + system name length + 200 for padding / metadata
        $len = 200 + strlen((string) $record['message']) + strlen($this->systemName);

        if ($len > $this->maxLength) {
            $message->setShortMessage(Utils::substr($record['message'], 0, $this->maxLength));
        }

        if ($this->gelfVersion === 1) {
            if (isset($record['channel'])) {
                $message->setFacility($record['channel']);
            }
            if (isset($extra['line'])) {
                $message->setLine($extra['line']);
                unset($extra['line']);
            }
            if (isset($extra['file'])) {
                $message->setFile($extra['file']);
                unset($extra['file']);
            }
        } else {
            $message->setAdditional('facility', $record['channel']);
        }

        foreach ($extra as $key => $val) {
            $val = is_scalar($val) || null === $val ? $val : $this->toJson($val);
            $len = strlen($this->extraPrefix . $key . $val);
            if ($len > $this->maxLength) {
                $message->setAdditional($this->extraPrefix . $key, Utils::substr((string) $val, 0, $this->maxLength));

                continue;
            }
            $message->setAdditional($this->extraPrefix . $key, $val);
        }

        foreach ($context as $key => $val) {
            $val = is_scalar($val) || null === $val ? $val : $this->toJson($val);
            $len = strlen($this->contextPrefix . $key . $val);
            if ($len > $this->maxLength) {
                $message->setAdditional($this->contextPrefix . $key, Utils::substr((string) $val, 0, $this->maxLength));

                continue;
            }
            $message->setAdditional($this->contextPrefix . $key, $val);
        }

        if ($this->gelfVersion === 1) {
            /** @phpstan-ignore-next-line */
            if (null === $message->getFile() && isset($context['exception']['file'])) {
                if (preg_match("/^(.+):([0-9]+)$/", $context['exception']['file'], $matches)) {
                    $message->setFile($matches[1]);
                    $message->setLine($matches[2]);
                }
            }
        }

        return $message;
    }
}
