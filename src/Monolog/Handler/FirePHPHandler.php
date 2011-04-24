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
     * Whether or not Wildfire vendor-specific headers have been generated & sent yet
     */
    private $initialized = false;

    /**
     * Header prefix for Wildfire to recognize & parse headers
     */
    private $prefix = 'X-Wf';

    /**
     * Shared static message index between potentially multiple handlers
     */
    private static $messageIndex = 1;

    /**
     * Function, Method or Closure for sending the header
     */
    private $writer;

    /**
     * @param integer $level  The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     * @param mixed   $writer Function, Method or Closure to use for sending headers
     */
    public function __construct($level = Logger::DEBUG, $bubble = false, $writer = null)
    {
        $this->level = $level;
        $this->bubble = $bubble;
        $this->writer = $writer;
    }

    /**
     * Base header creation function used by init headers & record headers
     *
     * @var Array $meta Wildfire Plugin, Protocol & Structure Indexes
     * @var String $message Log message
     * @return String Complete header string ready for the client
     */
    protected function createHeader(Array $meta, $message)
    {
        $header = sprintf('%s-%s', $this->prefix, join('-', $meta));
        
        return array($header => $message);
    }

    /**
     * Creates message header from record
     * 
     * @see createHeader()
     * @var Array $record
     */
    protected function createRecordHeader(Array $record)
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
        return new WildfireFormatter();
    }

    /**
     * Wildfire initialization headers to enable message parsing
     *
     * @see createHeader()
     * @see sendHeader()
     * @return Array
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
     * @var String $header
     * @var String $content
     * @return Boolean False if headers are already sent, true if header are sent successfully
     */
    protected function sendHeader($header, $content)
    {
        if (headers_sent()) {
            return false;
        } else if ($writer = $this->getWriter()) {
                call_user_func_array($writer, array($header, $content));
        } else {
            header(sprintf('%s: %s', $header, $content));
        }
        
        return true;
    }

    /**
     * Creates & sends header for a record, ensuring init headers have been sent prior
     *
     * @see sendHeader()
     * @see sendInitHeaders()
     * @var Array $record
     */
    protected function write(Array $record)
    {
        // WildFire-specific headers must be sent prior to any messages
        if (! $this->initialized) {
            foreach ($this->getInitHeaders() as $header => $content) {
                $this->sendHeader($header, $content);
            }
            
            $this->initialized = true;
        }
        
        foreach ($this->createRecordHeader($record) as $header => $content) {
            $this->sendHeader($header, $content);
        }
    }

    /**
     * @return mixed Writer used for sending headers
     */
    public function getWriter()
    {
        return $this->writer;
    }

    /**
     * @var mixed Function, Method or Closure to use for sending headers
     */
    public function setWriter($writer)
    {
        $this->writer = $writer;
    }

}