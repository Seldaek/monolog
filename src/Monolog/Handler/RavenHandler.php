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
use Monolog\Formatter\RavenFormatter;
use \Raven_Client;

/**
 * Handler to send messages to a Sentry (https://github.com/dcramer/sentry) server
 * using raven-php (https://github.com/getsentry/raven-php)
 *
 * @author Marc Abramowitz <marc@marc-abramowitz.com>
 */
class RavenHandler extends AbstractProcessingHandler
{
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
    public function close()
    {
        $this->ravenClient = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if ($record['level'] == Logger::ERROR && isset($record['context']['exception'])) {
            $this->ravenClient->captureException($record['context']['exception']);
        } else {
            $this->ravenClient->captureMessage(
                $record['formatted']['message'],
                $params = $record,
                $level = $record['formatted']['level'],
                $stack = true
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new RavenFormatter();
    }
}
