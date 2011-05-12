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
use Monolog\Formatter\PassthruFormatter;

/**
 * Simple Insight Handler that uses FirePHP 1.0.
 *
 * @author Christoph Dorn (@cadorn) <christoph@christophdorn.com>
 */
class InsightHandler extends AbstractHandler
{
    /**
     * Translates Monolog log levels to insight levels.
     */
    private $logLevels = array(
        Logger::DEBUG    => 'log',
        Logger::INFO     => 'info',
        Logger::WARNING  => 'warn',
        Logger::ERROR    => 'error',
        Logger::CRITICAL => 'error',
        Logger::ALERT    => 'error',
    );

    /**
     * The Insight context to relay all messages to
     * @var Insight_Message
     */
    protected $insightContext = null;

    /**
     * @param string $config Configuration array with the following keys:
     *     to: The context to send the messages to. Values: 'page' (default), 'request'
     *     console: The name of the console to send messages to. Any string.
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($config = false, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        if (!class_exists('Insight_Helper')) {
            throw new Exception("FirePHP 1.0 must be loaded prior to instanciating Monolog/Handler/InsightHandler");
        }
        $this->insightContext = \Insight_Helper
            ::to(($config && !empty($config['to'])) ? $config['to'] : 'page')
            ->console(($config && !empty($config['console'])) ? $config['console'] : 'Monolog');
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter()
    {
        return new PassthruFormatter();
    }

    /**
     * Send a record via insight
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        $this->insightContext->options(array(
            'priority' => $this->logLevels[$record['level']],
            'encoder.trace.offsetAdjustment' => 4
        ))->log($record['message']['message']);
    }

    /**
     * @see Insight API at http://reference.developercompanion.com/#/Tools/FirePHPCompanion/API/
     * @param string $name The context to log to. Typically 'page' or 'request' among others.
     */
    public static function to($name)
    {
        return \Insight_Helper::to($name);
    }
}