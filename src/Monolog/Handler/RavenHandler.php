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
use Monolog\Handler\AbstractProcessingHandler;
use Raven_Client;

/**
 * Handler to send messages to a Sentry (https://github.com/dcramer/sentry) server
 * using raven-php (https://github.com/getsentry/raven-php)
 *
 * @author Marc Abramowitz <marc@marc-abramowitz.com>
 */
class RavenHandler extends AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    private $logLevels = array(
        Logger::DEBUG     => Raven_Client::DEBUG,
        Logger::INFO      => Raven_Client::INFO,
        Logger::NOTICE    => Raven_Client::INFO,
        Logger::WARNING   => Raven_Client::WARNING,
        Logger::ERROR     => Raven_Client::ERROR,
        Logger::CRITICAL  => Raven_Client::FATAL,
        Logger::ALERT     => Raven_Client::FATAL,
        Logger::EMERGENCY => Raven_Client::FATAL,
    );

    /**
     * @var Raven_Client the client object that sends the message to the server
     */
    protected $ravenClient;

    /**
     * @param Raven_Client $ravenClient
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Raven_Client $ravenClient, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->ravenClient = $ravenClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $this->ravenClient->captureMessage(
            $record['formatted'],
            array(),                              // $params - not used
            $this->logLevels[$record['level']],   // $level
            false                                 // $stack
        );
        if ($record['level'] >= Logger::ERROR && isset($record['context']['exception'])) {
            $this->ravenClient->captureException($record['context']['exception']);
        }
    }
}
