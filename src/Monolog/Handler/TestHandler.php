<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

/**
 * Used for testing purposes.
 *
 * It records all records and gives you access to them for verification.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @method boolean hasEmergency($record)
 * @method boolean hasAlert($record)
 * @method boolean hasCritical($record)
 * @method boolean hasError($record)
 * @method boolean hasWarning($record)
 * @method boolean hasNotice($record)
 * @method boolean hasInfo($record)
 * @method boolean hasDebug($record)
 *
 * @method boolean hasEmergencyRecords()
 * @method boolean hasAlertRecords()
 * @method boolean hasCriticalRecords()
 * @method boolean hasErrorRecords()
 * @method boolean hasWarningRecords()
 * @method boolean hasNoticeRecords()
 * @method boolean hasInfoRecords()
 * @method boolean hasDebugRecords()
 *
 * @method boolean hasEmergencyThatContains($message)
 * @method boolean hasAlertThatContains($message)
 * @method boolean hasCriticalThatContains($message)
 * @method boolean hasErrorThatContains($message)
 * @method boolean hasWarningThatContains($message)
 * @method boolean hasNoticeThatContains($message)
 * @method boolean hasInfoThatContains($message)
 * @method boolean hasDebugThatContains($message)
 *
 * @method boolean hasEmergencyThatMatches($message)
 * @method boolean hasAlertThatMatches($message)
 * @method boolean hasCriticalThatMatches($message)
 * @method boolean hasErrorThatMatches($message)
 * @method boolean hasWarningThatMatches($message)
 * @method boolean hasNoticeThatMatches($message)
 * @method boolean hasInfoThatMatches($message)
 * @method boolean hasDebugThatMatches($message)
 *
 * @method boolean hasEmergencyThatPasses($message)
 * @method boolean hasAlertThatPasses($message)
 * @method boolean hasCriticalThatPasses($message)
 * @method boolean hasErrorThatPasses($message)
 * @method boolean hasWarningThatPasses($message)
 * @method boolean hasNoticeThatPasses($message)
 * @method boolean hasInfoThatPasses($message)
 * @method boolean hasDebugThatPasses($message)
 */
class TestHandler extends AbstractProcessingHandler
{
    protected $records = array();
    protected $recordsByLevel = array();

    public function getRecords()
    {
        return $this->records;
    }

    protected function hasRecordRecords($level)
    {
        return isset($this->recordsByLevel[$level]);
    }

    protected function hasRecord($record, $level)
    {
        if (is_array($record)) {
            $record = $record['message'];
        }

        return $this->hasRecordThatPasses(function ($rec) use ($record) {
            return $rec['message'] === $record;
        }, $level);
    }

    public function hasRecordThatContains($message, $level)
    {
        return $this->hasRecordThatPasses(function ($rec) use ($message) {
            return strpos($rec['message'], $message) !== false;
        }, $level);
    }

    public function hasRecordThatMatches($regex, $level)
    {
        return $this->hasRecordThatPasses(function ($rec) use ($regex) {
            return preg_match($regex, $rec['message']) > 0;
        }, $level);
    }

    public function hasRecordThatPasses($predicate, $level)
    {
        if (!is_callable($predicate)) {
            throw new \InvalidArgumentException("Expected a callable for hasRecordThatSucceeds");
        }

        if (!isset($this->recordsByLevel[$level])) {
            return false;
        }

        foreach ($this->recordsByLevel[$level] as $i => $rec) {
            if (call_user_func($predicate, $rec, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $this->recordsByLevel[$record['level']][] = $record;
        $this->records[] = $record;
    }

    public function __call($method, $args)
    {
        if (preg_match('/(.*)(Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)(.*)/', $method, $matches) > 0) {
            $genericMethod = $matches[1] . 'Record' . $matches[3];
            $level = constant('Monolog\Logger::' . strtoupper($matches[2]));
            if (method_exists($this, $genericMethod)) {
                $args[] = $level;

                return call_user_func_array(array($this, $genericMethod), $args);
            }
        }

        throw new \BadMethodCallException('Call to undefined method ' . get_class($this) . '::' . $method . '()');
    }
}
