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

use MongoDB\BSON\UTCDateTime;
use Monolog\Utils;

/**
 * Formats a record for use with the MongoDBHandler.
 *
 * @author Florian Plattner <me@florianplattner.de>
 */
class MongoDBFormatter implements FormatterInterface
{
    private $exceptionTraceAsString;
    private $maxNestingLevel;
    private $isLegacyMongoExt;

    /**
     * @param int  $maxNestingLevel        0 means infinite nesting, the $record itself is level 1, $record['context'] is 2
     * @param bool $exceptionTraceAsString set to false to log exception traces as a sub documents instead of strings
     */
    public function __construct(int $maxNestingLevel = 3, bool $exceptionTraceAsString = true)
    {
        $this->maxNestingLevel = max($maxNestingLevel, 0);
        $this->exceptionTraceAsString = $exceptionTraceAsString;

        $this->isLegacyMongoExt = extension_loaded('mongodb') && version_compare(phpversion('mongodb'), '1.1.9', '<=');
    }

    /**
     * {@inheritDoc}
     */
    public function format(array $record): array
    {
        return $this->formatArray($record);
    }

    /**
     * {@inheritDoc}
     */
    public function formatBatch(array $records): array
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }

        return $records;
    }

    /**
     * @return array|string Array except when max nesting level is reached then a string "[...]"
     */
    protected function formatArray(array $record, int $nestingLevel = 0)
    {
        if ($this->maxNestingLevel == 0 || $nestingLevel <= $this->maxNestingLevel) {
            foreach ($record as $name => $value) {
                if ($value instanceof \DateTimeInterface) {
                    $record[$name] = $this->formatDate($value, $nestingLevel + 1);
                } elseif ($value instanceof \Throwable) {
                    $record[$name] = $this->formatException($value, $nestingLevel + 1);
                } elseif (is_array($value)) {
                    $record[$name] = $this->formatArray($value, $nestingLevel + 1);
                } elseif (is_object($value)) {
                    $record[$name] = $this->formatObject($value, $nestingLevel + 1);
                }
            }
        } else {
            $record = '[...]';
        }

        return $record;
    }

    protected function formatObject($value, int $nestingLevel)
    {
        $objectVars = get_object_vars($value);
        $objectVars['class'] = Utils::getClass($value);

        return $this->formatArray($objectVars, $nestingLevel);
    }

    protected function formatException(\Throwable $exception, int $nestingLevel)
    {
        $formattedException = [
            'class' => Utils::getClass($exception),
            'message' => $exception->getMessage(),
            'code' => (int) $exception->getCode(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
        ];

        if ($this->exceptionTraceAsString === true) {
            $formattedException['trace'] = $exception->getTraceAsString();
        } else {
            $formattedException['trace'] = $exception->getTrace();
        }

        return $this->formatArray($formattedException, $nestingLevel);
    }

    protected function formatDate(\DateTimeInterface $value, int $nestingLevel): UTCDateTime
    {
        if ($this->isLegacyMongoExt) {
            return $this->legacyGetMongoDbDateTime($value);
        }

        return $this->getMongoDbDateTime($value);
    }

    private function getMongoDbDateTime(\DateTimeInterface $value): UTCDateTime
    {
        return new UTCDateTime((int) floor(((float) $value->format('U.u')) * 1000));
    }

    /**
     * This is needed to support MongoDB Driver v1.19 and below
     *
     * See https://github.com/mongodb/mongo-php-driver/issues/426
     *
     * It can probably be removed in 2.1 or later once MongoDB's 1.2 is released and widely adopted
     */
    private function legacyGetMongoDbDateTime(\DateTimeInterface $value): UTCDateTime
    {
        $milliseconds = floor(((float) $value->format('U.u')) * 1000);

        $milliseconds = (PHP_INT_SIZE == 8) //64-bit OS?
            ? (int) $milliseconds
            : (string) $milliseconds;

        return new UTCDateTime($milliseconds);
    }
}
