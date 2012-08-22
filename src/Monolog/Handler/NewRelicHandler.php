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

/**
 * Class to record a log on a NewRelic application
 *
 * @see https://newrelic.com/docs/php/new-relic-for-php
 */
class NewRelicHandler extends AbstractProcessingHandler
{
    protected $fallbackHandler;
    
    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if (extension_loaded('newrelic')) {
            newrelic_notice_error($record['message']);

            foreach ($record['context'] as $key => $parameter) {
                newrelic_add_custom_parameter($key, $parameter);
            }
        } elseif ($this->fallbackHandler instanceOf AbstractProcessingHandler) {
            $this->fallbackHandler->write($record);
        }
    }

    /**
     * Sets the fallback handler to be used to log informations if the New Relic
     * extension is not available.
     *
     * @param Monolog\Handler\AbstractProcessingHandler $handler 
     */
    public function setFallbackHandler(AbstractProcessingHandler $handler)
    {
        $this->fallbackHandler = $handler;
    }
}
