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

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;

/**
 * Sends notifications through the Flowdock push API
 *
 * Notes:
 * API token - Flowdock API token
 *
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 * @see https://www.flowdock.com/api/push
 */
class FlowdockHandler extends AbstractProcessingHandler
{
    /**
     * @var string
     */
    protected $apiToken;

    /**
     * @param string     $apiToken
     * @param bool|int   $level  The minimum logging level at which this handler will be triggered
     * @param bool       $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($apiToken, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->apiToken = $apiToken;
    }

    /**
     * @param array $record
     */
    public function write(array $record)
    {
        $uri = 'https://api.flowdock.com/v1/messages/team_inbox/' . $this->apiToken;

        $tags = array(
            '#logs',
            '#' . strtolower($record['level_name']),
            '#' . $record['channel'],
        );

        foreach ($record['extra'] as $key => $value) {
            if ($key != 'requestIdent') {
                $tags[] = '#' . $value;
            }
        }

        $data = array(
            "subject" => sprintf("[%s] %s", $record['level_name'], $record['message']),
            "content" => $record['message'],
            "tags" => $tags
        );

        // add all extras
        foreach ($record['extra'] as $key => $extra) {
            $data[$key] = $extra;
        }

        $streamContext = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'headers' => "Content-type: application/json\r\n",
                'content' => json_encode($data)
            )
        ));

        $streamHandle = fopen($uri, 'r', false, $streamContext);
        stream_get_contents($streamHandle);
        fclose($streamHandle);
    }
}
