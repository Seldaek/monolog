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

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;

/**
 * Handler sending logs to Zend Monitor
 *
 * @author  Christian Bergau <cbergau86@gmail.com>
 */
class ZendMonitorHandler extends AbstractProcessingHandler
{
    /**
     * Monolog level / ZendMonitor Custom Event priority map
     *
     * @var array
     */
    protected $levelMap = array(
        Logger::DEBUG     => 1,
        Logger::INFO      => 2,
        Logger::NOTICE    => 3,
        Logger::WARNING   => 4,
        Logger::ERROR     => 5,
        Logger::CRITICAL  => 6,
        Logger::ALERT     => 7,
        Logger::EMERGENCY => 0,
    );

    /**
     * Is application running on a zend server?
     *
     * @var bool
     */
    protected $isZendServer = false;

    /**
     * Construct
     *
     * @param   int     $level
     * @param   bool    $bubble
     */
    public function __construct($level = Logger::DEBUG, $bubble = true)
    {
        $this->isZendServer = function_exists('zend_monitor_custom_event');
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if ($this->isZendServer()) {
            $formatter = new NormalizerFormatter();
            $this->writeZendMonitorCustomEvent($formatter->format($record));
        }
    }

    /**
     * Write a record to Zend Monitor
     *
     * @param   array   $record
     */
    protected function writeZendMonitorCustomEvent(array $record)
    {
        zend_monitor_custom_event($this->levelMap[$record['level']], $record['message'], $record['formatted']);
    }

    /**
     * Is Zend Server?
     *
     * @return bool
     */
    public function isZendServer()
    {
        return $this->isZendServer;
    }
}
