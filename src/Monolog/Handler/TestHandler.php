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

use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Monolog\LogRecord;

/**
 * Used for testing purposes.
 *
 * It records all records and gives you access to them for verification.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @method bool hasEmergency($record)
 * @method bool hasAlert($record)
 * @method bool hasCritical($record)
 * @method bool hasError($record)
 * @method bool hasWarning($record)
 * @method bool hasNotice($record)
 * @method bool hasInfo($record)
 * @method bool hasDebug($record)
 *
 * @method bool hasEmergencyRecords()
 * @method bool hasAlertRecords()
 * @method bool hasCriticalRecords()
 * @method bool hasErrorRecords()
 * @method bool hasWarningRecords()
 * @method bool hasNoticeRecords()
 * @method bool hasInfoRecords()
 * @method bool hasDebugRecords()
 *
 * @method bool hasEmergencyThatContains($message)
 * @method bool hasAlertThatContains($message)
 * @method bool hasCriticalThatContains($message)
 * @method bool hasErrorThatContains($message)
 * @method bool hasWarningThatContains($message)
 * @method bool hasNoticeThatContains($message)
 * @method bool hasInfoThatContains($message)
 * @method bool hasDebugThatContains($message)
 *
 * @method bool hasEmergencyThatMatches($message)
 * @method bool hasAlertThatMatches($message)
 * @method bool hasCriticalThatMatches($message)
 * @method bool hasErrorThatMatches($message)
 * @method bool hasWarningThatMatches($message)
 * @method bool hasNoticeThatMatches($message)
 * @method bool hasInfoThatMatches($message)
 * @method bool hasDebugThatMatches($message)
 *
 * @method bool hasEmergencyThatPasses($message)
 * @method bool hasAlertThatPasses($message)
 * @method bool hasCriticalThatPasses($message)
 * @method bool hasErrorThatPasses($message)
 * @method bool hasWarningThatPasses($message)
 * @method bool hasNoticeThatPasses($message)
 * @method bool hasInfoThatPasses($message)
 * @method bool hasDebugThatPasses($message)
 */
class TestHandler extends AbstractProcessingHandler
{
    /** @var LogRecord[] */
    protected array $records = [];
    /** @phpstan-var array<value-of<Level::VALUES>, LogRecord[]> */
    protected array $recordsByLevel = [];
    private bool $skipReset = false;

    /**
     * @return array<LogRecord>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function clear(): void
    {
        $this->records = [];
        $this->recordsByLevel = [];
    }

    public function reset(): void
    {
        if (!$this->skipReset) {
            $this->clear();
        }
    }

    public function setSkipReset(bool $skipReset): void
    {
        $this->skipReset = $skipReset;
    }

    /**
     * @param int|string|Level|LogLevel::* $level Logging level value or name
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function hasRecords(int|string|Level $level): bool
    {
        return isset($this->recordsByLevel[Logger::toMonologLevel($level)->value]);
    }

    /**
     * @param string|array $recordAssertions Either a message string or an array containing message and optionally context keys that will be checked against all records
     *
     * @phpstan-param array{message: string, context?: mixed[]}|string $recordAssertions
     */
    public function hasRecord(string|array $recordAssertions, Level $level): bool
    {
        if (is_string($recordAssertions)) {
            $recordAssertions = ['message' => $recordAssertions];
        }

        return $this->hasRecordThatPasses(function (LogRecord $rec) use ($recordAssertions) {
            if ($rec->message !== $recordAssertions['message']) {
                return false;
            }
            if (isset($recordAssertions['context']) && $rec->context !== $recordAssertions['context']) {
                return false;
            }

            return true;
        }, $level);
    }

    public function hasRecordThatContains(string $message, Level $level): bool
    {
        return $this->hasRecordThatPasses(fn (LogRecord $rec) => str_contains($rec->message, $message), $level);
    }

    public function hasRecordThatMatches(string $regex, Level $level): bool
    {
        return $this->hasRecordThatPasses(fn (LogRecord $rec) => preg_match($regex, $rec->message) > 0, $level);
    }

    /**
     * @phpstan-param callable(LogRecord, int): mixed $predicate
     */
    public function hasRecordThatPasses(callable $predicate, Level $level): bool
    {
        $level = Logger::toMonologLevel($level);

        if (!isset($this->recordsByLevel[$level->value])) {
            return false;
        }

        foreach ($this->recordsByLevel[$level->value] as $i => $rec) {
            if ((bool) $predicate($rec, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $this->recordsByLevel[$record->level->value][] = $record;
        $this->records[] = $record;
    }

    /**
     * @param mixed[] $args
     */
    public function __call(string $method, array $args): bool
    {
        if (preg_match('/(.*)(Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)(.*)/', $method, $matches) > 0) {
            $genericMethod = $matches[1] . ('Records' !== $matches[3] ? 'Record' : '') . $matches[3];
            $level = constant(Level::class.'::' . $matches[2]);
            $callback = [$this, $genericMethod];
            if (is_callable($callback)) {
                $args[] = $level;

                return call_user_func_array($callback, $args);
            }
        }

        throw new \BadMethodCallException('Call to undefined method ' . get_class($this) . '::' . $method . '()');
    }
}
