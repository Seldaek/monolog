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
use Monolog\LogRecord;

/**
 * Serializes a log message according to Wildfire's header requirements
 *
 * @author Eric Clemmons (@ericclemmons) <eric@uxdriven.com>
 * @author Christophe Coevoet <stof@notk.org>
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 *
 * @phpstan-import-type Level from \Monolog\Logger
 */
class WildfireFormatter extends NormalizerFormatter
{
    /**
     * Translates Monolog log levels to Wildfire levels.
     *
     * @var array<Level, string>
     */
    private $logLevels = [
        Logger::DEBUG     => 'LOG',
        Logger::INFO      => 'INFO',
        Logger::NOTICE    => 'INFO',
        Logger::WARNING   => 'WARN',
        Logger::ERROR     => 'ERROR',
        Logger::CRITICAL  => 'ERROR',
        Logger::ALERT     => 'ERROR',
        Logger::EMERGENCY => 'ERROR',
    ];

    /**
     * @param string|null $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct(?string $dateFormat = null)
    {
        parent::__construct($dateFormat);

        // http headers do not like non-ISO-8559-1 characters
        $this->removeJsonEncodeOption(JSON_UNESCAPED_UNICODE);
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function format(LogRecord $record): string
    {
        // Retrieve the line and file if set and remove them from the formatted extra
        $file = $line = '';
        if (isset($record->extra['file'])) {
            $file = $record->extra['file'];
            unset($record->extra['file']);
        }
        if (isset($record->extra['line'])) {
            $line = $record->extra['line'];
            unset($record->extra['line']);
        }

        $message = ['message' => $record->message];
        $handleError = false;
        if (count($record->context) > 0) {
            $message['context'] = $this->normalize($record->context);
            $handleError = true;
        }
        if (count($record->extra) > 0) {
            $message['extra'] = $this->normalize($record->extra);
            $handleError = true;
        }
        if (count($message) === 1) {
            $message = reset($message);
        }

        if (is_array($message) && isset($message['context']['table'])) {
            $type  = 'TABLE';
            $label = $record->channel .': '. $record->message;
            $message = $message['context']['table'];
        } else {
            $type  = $this->logLevels[$record->level];
            $label = $record->channel;
        }

        // Create JSON object describing the appearance of the message in the console
        $json = $this->toJson([
            [
                'Type'  => $type,
                'File'  => $file,
                'Line'  => $line,
                'Label' => $label,
            ],
            $message,
        ], $handleError);

        // The message itself is a serialization of the above JSON object + it's length
        return sprintf(
            '%d|%s|',
            strlen($json),
            $json
        );
    }

    /**
     * {@inheritDoc}
     *
     * @phpstan-return never
     */
    public function formatBatch(array $records)
    {
        throw new \BadMethodCallException('Batch formatting does not make sense for the WildfireFormatter');
    }

    /**
     * {@inheritDoc}
     *
     * @return null|scalar|array<mixed[]|scalar|null>|object
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if (is_object($data) && !$data instanceof \DateTimeInterface) {
            return $data;
        }

        return parent::normalize($data, $depth);
    }
}
