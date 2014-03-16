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
 * Sends notifications through the Flowdock api to a team inbox
 *
 * Notes:
 * API token - Flowdock API token
 * From      - Email used to send the message (from)
 * Name      - Name used to send the message (from)
 *
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 * @see    https://www.flowdock.com/api/team-inbox
 */
class FlowdockHandler extends AbstractProcessingHandler
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $from;

    /**
     * @var string
     */
    private $source;

    /**
     * @param string  $token  Flowdock API Token
     * @param string  $from   Email used in the "from" field
     * @param string  $source Name used in the "from" field, e.g. an environment name
     * @param int     $level  The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($token, $from, $source = 'Monolog', $level = Logger::CRITICAL, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->token = $token;
        $this->from = $from;
        $this->source = $source;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if (!extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required to use the FlowdockHandler');
        }

        $url = 'https://api.flowdock.com/v1/messages/team_inbox/' . $this->token;

        $tags = array(
            '#' . $record['level_name'],
            '#' . $record['channel'],
            '#monolog',
            '@everyone'
        );

        $subject = sprintf(
            'in %s: %s - %s',
            $this->source,
            $record['level_name'],
            $this->getShortMessage($record['message'])
        );

        $postData = array(
            'source' => $this->source,
            'from_address' => $this->from,
            'subject' => $subject,
            'content' => $record['message'],
            'tags'    => $tags,
        );
        $data = json_encode($postData);
        $length = strlen($data);
        
        $context = stream_context_create(
            array(
                'http' => array(
                    'method' => "POST",
                    'header' => "Content-type: application/json\r\nContent-length: $length\r\n",
                    'content' => $data,
                    'ignore_errors' => true,
                )
            )
        );

        $handle = @fopen($url, 'r', false, $context);

        if (is_resource($handle)) {
            $return = json_decode(stream_get_contents($handle), true);

            // OK returns an empty JSON object
            if (count($return) > 0) {
                throw new \RuntimeException("Flowdock API error: " . json_encode($return));
            }
        }

        @fclose($handle);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function getShortMessage($message)
    {
        $maxLength = 45;

        if (strlen($message) > $maxLength) {
            $message = substr($message, 0, $maxLength - 4) . ' ...';
        }

        return $message;
    }
}
