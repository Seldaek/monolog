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
use Monolog\Handler\InsightHandler\NullMessage;

/**
 * Insight Handler that uses FirePHP 1.0's Insight API to send messages to supporting clients.
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
     * Insight configuration information
     * @see __construct()
     * @var array
     */
    protected $config = null;

    /**
     * @param string $config Configuration array with the following keys:
     *     context: The context to send the messages to. Values: 'page' (default), 'request'
     *     console: The name of the console to send messages to. Any string.
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($config = false, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->config = $config;
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
    function handle(array $record)
    {
        if (!class_exists('\Insight_Helper', false)) {
            return false;
        }
        \Insight_Helper
            ::to((!empty($this->config['context'])) ? $this->config['context'] : 'page')
            ->console((!empty($this->config['console'])) ? $this->config['console'] : 'Monolog: ' . $record['channel'])
            ->options(array(
                'priority' => $this->logLevels[$record['level']],
                'encoder.trace.offsetAdjustment' => 3
            ))
            ->log($record['message']);
        return true;
    }

    /**
     * Get an instance of the Insight API for a specific message context. This can then be used
     * to send complex data structures to clients that support the Insight Intelligence System.
     * The context specifies where messages are to be sent. The most common contexts are:
     * 
     *   * 'page' - Show messages in a page-based console. e.g. Firebug Console
     *   * 'request' - Shows messages in a request-based console. e.g. DeveloperCompanion Request Inspector
     * 
     * @see Insight API at http://reference.developercompanion.com/#/Tools/FirePHPCompanion/API/ where 'InsightHandler::getContext()' is equivalent to 'FirePHP::to()'.
     * @param string $name The name of the context to log to.
     */
    public static function getContext($name = 'page')
    {
        if (!class_exists('\Insight_Helper', false)) {
            return new NullMessage();
        }
        return \Insight_Helper::to($name);
    }
}