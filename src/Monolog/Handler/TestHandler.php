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

use Monolog\Logger;

/**
 * Used for testing purposes.
 *
 * It records all records and gives you access to them for verification.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class TestHandler extends AbstractProcessingHandler
{
    /**
     * @var array
     */
    protected $records = array();

    /**
     * @var array
     */
    protected $recordsByLevel = array();

    /**
     * @return array
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasEmergency($record)
    {
        return $this->hasRecord($record, Logger::EMERGENCY);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasAlert($record)
    {
        return $this->hasRecord($record, Logger::ALERT);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasCritical($record)
    {
        return $this->hasRecord($record, Logger::CRITICAL);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasError($record)
    {
        return $this->hasRecord($record, Logger::ERROR);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasWarning($record)
    {
        return $this->hasRecord($record, Logger::WARNING);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasNotice($record)
    {
        return $this->hasRecord($record, Logger::NOTICE);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasInfo($record)
    {
        return $this->hasRecord($record, Logger::INFO);
    }

    /**
     * @param array|string $record
     *
     * @return bool
     */
    public function hasDebug($record)
    {
        return $this->hasRecord($record, Logger::DEBUG);
    }

    /**
     * @return bool
     */
    public function hasEmergencyRecords()
    {
        return isset($this->recordsByLevel[Logger::EMERGENCY]);
    }

    /**
     * @return bool
     */
    public function hasAlertRecords()
    {
        return isset($this->recordsByLevel[Logger::ALERT]);
    }

    /**
     * @return bool
     */
    public function hasCriticalRecords()
    {
        return isset($this->recordsByLevel[Logger::CRITICAL]);
    }

    /**
     * @return bool
     */
    public function hasErrorRecords()
    {
        return isset($this->recordsByLevel[Logger::ERROR]);
    }

    /**
     * @return bool
     */
    public function hasWarningRecords()
    {
        return isset($this->recordsByLevel[Logger::WARNING]);
    }

    /**
     * @return bool
     */
    public function hasNoticeRecords()
    {
        return isset($this->recordsByLevel[Logger::NOTICE]);
    }

    /**
     * @return bool
     */
    public function hasInfoRecords()
    {
        return isset($this->recordsByLevel[Logger::INFO]);
    }

    /**
     * @return bool
     */
    public function hasDebugRecords()
    {
        return isset($this->recordsByLevel[Logger::DEBUG]);
    }

    /**
     * @param array|string $record
     * @param integer      $level
     *
     * @return bool
     */
    protected function hasRecord($record, $level)
    {
        if (!isset($this->recordsByLevel[$level])) {
            return false;
        }

        if (is_array($record)) {
            $record = $record['message'];
        }

        foreach ($this->recordsByLevel[$level] as $rec) {
            if ($rec['message'] === $record) {
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
        $this->records[]                          = $record;
    }
}
