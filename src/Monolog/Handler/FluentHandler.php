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

use Fluent\Logger\FluentLogger;
use Monolog\Logger;

/**
 * Handler to send messages to a Fluentd (http://www.fluentd.org/) server
 *
 * Requires https://github.com/fluent/fluent-logger-php
 *
 * @author Dai Akatsuka <d.akatsuka@gmail.com>
 * @author Yegor Tokmakov <yegor@tokmakov.biz>
 */
class FluentHandler extends AbstractProcessingHandler
{
    /**
     * @var FluentLogger
     */
    private $logger;

    /**
     * Initialize Handler
     *
     * @param FluentLogger $logger
     * @param bool|int     $level
     * @param bool         $bubble
     */
    public function __construct(
        FluentLogger $logger = null,
        $level  = Logger::DEBUG,
        $bubble = true
    )
    {
        parent::__construct($level, $bubble);

        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $data = $record['context'];
        $data['level'] = Logger::getLevelName($record['level']);
        $data['message'] = $record['message'];

        $this->logger->post($record['channel'], $data);
    }
}
