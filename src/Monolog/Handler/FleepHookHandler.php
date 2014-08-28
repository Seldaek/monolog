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
 * Sends logs to Fleep.io using Webhook integrations
 *
 * You'll need a Fleep.io account to use this handler.
 *
 * @see https://fleep.io/integrations/webhooks/ Fleep Webhooks Documentation
 * @author Ando Roots <ando@sqroot.eu>
 */
class FleepHookHandler extends AbstractProcessingHandler
{

    const HOOK_ENDPOINT = 'https://fleep.io/hook/';

    /**
     * @var string Webhook token (specifies the conversation where logs are sent)
     */
    protected $token;

    /**
     * @var string Full URI to the webhook endpoint (HOOK_ENDPOINT + token)
     */
    protected $url;

    /**
     * @var array Default options to Curl
     */
    protected $curlOptions = array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
        CURLOPT_RETURNTRANSFER => true
    );

    /**
     * Construct a new Fleep.io Handler.
     *
     * For instructions on how to create a new web hook in your conversations
     * see https://fleep.io/integrations/webhooks/
     *
     * @param string $token Webhook token
     * @param bool|int $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @throws \LogicException
     */
    public function __construct($token, $level = Logger::DEBUG, $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use FleepHookHandler');
        }

        $this->token = $token;
        $this->url = self::HOOK_ENDPOINT . $this->token;

        parent::__construct($level, $bubble);
    }

    /**
     * @return array
     */
    public function getCurlOptions()
    {
        return $this->curlOptions;
    }

    /**
     * Returns the default formatter to use with this handler
     *
     * Overloaded to remove empty context and extra arrays from the end of the log message.
     *
     * @return LineFormatter
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter(null, null, true, true);

    }

    /**
     * Handles a log record
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        $this->send($record['formatted']);
    }


    /**
     * Prepares the record for sending to Fleep
     *
     * @param string $message The formatted log message to send
     */
    protected function send($message)
    {
        $this->addCurlOptions(
            array(
                CURLOPT_POSTFIELDS => http_build_query(array('message' => $message)),
                CURLOPT_URL => $this->url,
            )
        );

        $this->execCurl($this->curlOptions);

    }

    /**
     * Sends a new Curl request
     *
     * @param array $options Curl parameters, including the endpoint URL and POST payload
     */
    protected function execCurl(array $options)
    {
        $curl = curl_init();

        curl_setopt_array($curl, $options);

        curl_exec($curl);
        curl_close($curl);
    }


    /**
     * Adds or overwrites a curl option
     *
     * @param array $options An assoc array of Curl options, indexed by CURL_* constants
     * @return $this
     */
    public function addCurlOptions(array $options)
    {
        $this->curlOptions = array_replace(
            $this->curlOptions,
            $options
        );

        return $this;
    }
}
