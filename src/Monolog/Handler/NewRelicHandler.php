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

use Monolog\Handler\MissingExtensionException;

/**
 * Class to record a log on a NewRelic application
 *
 * @see https://newrelic.com/docs/php/new-relic-for-php
 */
class NewRelicHandler extends AbstractProcessingHandler
{    
    const ERROR_MISSING_EXTENSION = "The NewRelic PHP extension is not installed on this system, therefore you can't use the NewRelicHandler";
    const NEWRELIC_EXTENSION_NAME = 'newrelic';
    
    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if ($this->isNewRelicEnabled()) {
            newrelic_notice_error($record['message']);

            foreach ($record['context'] as $key => $parameter) {
                newrelic_add_custom_parameter($key, $parameter);
            }
            
            return;
        }
        
        throw new MissingExtensionException(self::ERROR_MISSING_EXTENSION);
    }
    
    /**
     * Checks whether the NewRelic extension is enabled in the system.
     * 
     * @return bool
     */
    protected function isNewRelicEnabled()
    {
        return (bool) extension_loaded(self::NEWRELIC_EXTENSION_NAME);
    }
}
