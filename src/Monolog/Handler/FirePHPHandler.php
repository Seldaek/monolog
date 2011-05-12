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
use Monolog\Formatter\WildfireFormatter;
use Monolog\Formatter\PassthruFormatter;

/**
 * Simple FirePHP Handler (http://www.firephp.org/), which uses the Wildfire protocol.
 *
 * @author Eric Clemmons (@ericclemmons) <eric@uxdriven.com>
 */
class FirePHPHandler extends AbstractHandler
{
    /**
     * WildFire JSON header message format
     */
    const PROTOCOL_URI = 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2';

    /**
     * FirePHP structure for parsing messages & their presentation
     */
    const STRUCTURE_URI = 'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1';

    /**
     * Must reference a "known" plugin, otherwise headers won't display in FirePHP
     */
    const PLUGIN_URI = 'http://meta.firephp.org/Wildfire/Plugin/ZendFramework/FirePHP/1.6.2';

    /**
     * Header prefix for Wildfire to recognize & parse headers
     */
    const HEADER_PREFIX = 'X-Wf';

    /**
     * Whether or not Wildfire vendor-specific headers have been generated & sent yet
     */
    protected static $initialized = false;

    /**
     * Shared static message index between potentially multiple handlers
     * @var int
     */
    protected static $messageIndex = 1;
    
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
        if ($this->insightContext !== false && class_exists('Insight_Helper')) {
            $this->insightContext = \FirePHP
                ::to(($config && isset($config['to'])) ? $config['to'] : 'page')
                ->console(($config && isset($config['console'])) ? $config['console'] : 'Monolog');
        }
    }

    /**
     * Base header creation function used by init headers & record headers
     *
     * @param array $meta Wildfire Plugin, Protocol & Structure Indexes
     * @param string $message Log message
     * @return array Complete header string ready for the client as key and message as value
     */
    protected function createHeader(array $meta, $message)
    {
        $header = sprintf('%s-%s', self::HEADER_PREFIX, join('-', $meta));

        return array($header => $message);
    }

    /**
     * Creates message header from record
     *
     * @see createHeader()
     * @param array $record
     * @return string
     */
    protected function createRecordHeader(array $record)
    {
        // Wildfire is extensible to support multiple protocols & plugins in a single request,
        // but we're not taking advantage of that (yet), so we're using "1" for simplicity's sake.
        return $this->createHeader(
            array(1, 1, 1, self::$messageIndex++),
            $record['message']
        );
    }

    protected function getDefaultFormatter()
    {
        // FirePHP 1.0
        if ($this->insightContext)
            return new PassthruFormatter();
        return new WildfireFormatter();
    }

    /**
     * Wildfire initialization headers to enable message parsing
     *
     * @see createHeader()
     * @see sendHeader()
     * @return array
     */
    protected function getInitHeaders()
    {
        // Initial payload consists of required headers for Wildfire
        return array_merge(
            $this->createHeader(array('Protocol', 1), self::PROTOCOL_URI),
            $this->createHeader(array(1, 'Structure', 1), self::STRUCTURE_URI),
            $this->createHeader(array(1, 'Plugin', 1), self::PLUGIN_URI)
        );
    }

    /**
     * Send header string to the client
     *
     * @param string $header
     * @param string $content
     */
    protected function sendHeader($header, $content)
    {
        if (!headers_sent()) {
            header(sprintf('%s: %s', $header, $content));
        }
    }

    /**
     * Creates & sends header for a record, ensuring init headers have been sent prior
     *
     * @see sendHeader()
     * @see sendInitHeaders()
     * @param array $record
     */
    protected function write(array $record)
    {
        // FirePHP 1.0
        if ($this->insightContext) {
            $levels = array(
                Logger::DEBUG    => 'log',
                Logger::INFO     => 'info',
                Logger::WARNING  => 'warn',
                Logger::ERROR    => 'error',
                Logger::CRITICAL => 'error',
                Logger::ALERT    => 'error'
            );
            $this->insightContext->options(array(
                'priority' => $levels[$record['level']],
                'encoder.trace.offsetAdjustment' => 4
            ))->log($record['message']['message']);
            return;
        }

        // WildFire-specific headers must be sent prior to any messages
        if (!self::$initialized) {
            foreach ($this->getInitHeaders() as $header => $content) {
                $this->sendHeader($header, $content);
            }

            self::$initialized = true;
        }

        $header = $this->createRecordHeader($record);
        $this->sendHeader(key($header), current($header));
    }
}