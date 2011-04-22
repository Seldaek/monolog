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

    const PROTOCOL_URI = 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2';

    const STRUCTURE_URI = 'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1';

    const PLUGIN_URI = 'http://meta.firephp.org/Wildfire/Plugin/ZendFramework/FirePHP/1.6.2';

    private $prefix = 'X-Wf';

    private $records = array();

    protected function createHeader(Array $meta, $message)
    {
        return sprintf(
            '%s-%s: %s',
            $this->prefix,
            join('-', $meta),
            $message
        );
    }

    protected function write(Array $record)
    {
        $this->records[] = $record;
    }
    
    public function close()
    {
        if (headers_sent()) {
            return false;
        } else {
            foreach ($this->getHeaders() as $header) {
                header($header);
            }
            
            return true;
        }
    }

    public function getHeaders()
    {
        // Wildfire is extensible to support multiple protocols & plugins in a single request,
        // but we're not taking advantage of that (yet) for simplicity's sake.
        // (It does help understanding header formatting, though!)
        $protocolIndex  = 1;
        $structureIndex = 1;
        $pluginIndex    = 1;
        $messageIndex   = 1;
        
        // Initial payload consists of required headers for Wildfire
        $headers = array(
            $this->createHeader(array('Protocol', $protocolIndex), self::PROTOCOL_URI),
            $this->createHeader(array($protocolIndex, 'Structure', $structureIndex), self::STRUCTURE_URI),
            $this->createHeader(array($protocolIndex, 'Plugin', $pluginIndex), self::PLUGIN_URI),
        );
        
        foreach ($this->records as $record) {
            $headers[] = $this->createHeader(
                array($protocolIndex, $structureIndex, $pluginIndex, $messageIndex++),
                $record['message']
            );
        }
        
        return $headers;
    }

    protected function getDefaultFormatter()
    {
        return new WildfireFormatter();
    }

}