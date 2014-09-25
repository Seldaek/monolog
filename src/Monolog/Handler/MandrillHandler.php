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
use GuzzleHttp\Client;

/**
 * MandrillHandler uses GuzzleHttp to send the emails to the Mandrill API
 *
 * @author Adam Nicholson <adamnicholson10@gmail.com>
 */
class MandrillHandler extends MailHandler
{
    protected $client;
    protected $message;

    /**
     * @param \GuzzleHttp\Client      $client  The Guzzle client
     * @oaram string                  $apiKey  A valid Mandrill API key
     * @param callable|\Swift_Message $message An example message for real messages, only the body will be replaced
     * @param integer                 $level   The minimum logging level at which this handler will be triggered
     * @param Boolean                 $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Client $client, $apiKey, $message, $level = Logger::ERROR, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->client  = $client;
        if (!$message instanceof \Swift_Message && is_callable($message)) {
            $message = call_user_func($message);
        }
        if (!$message instanceof \Swift_Message) {
            throw new \InvalidArgumentException('You must provide either a Swift_Message instance or a callable returning it');
        }
        $this->message = $message;
        $this->apiKey = $apiKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content, array $records)
    {
        $message = clone $this->message;
        $message->setBody($content);

        $this->client->post('https://mandrillapp.com/api/1.0/messages/send-raw.json', [
            'body' => [
                'key' => $this->apiKey,
                'raw_message' => (string) $message,
                'async' => false,
            ],
        ]);
    }
}