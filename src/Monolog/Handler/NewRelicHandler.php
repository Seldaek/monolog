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
 * Class to record a log on a NewRelic application
 *
 * @see https://docs.newrelic.com/docs/agents/php-agent
 */
class NewRelicHandler extends AbstractProcessingHandler
{
    /**
     * Name of the New Relic application that will receive logs from this handler.
     *
     * @var string
     */
    protected $appName;

    /**
     * Name of the current transaction
     *
     * @var string
     */
    protected $transactionName;

    /**
     * Some context and extra data is passed into the handler as arrays of values. Do we send them as is
     * (useful if we are using the API), or explode them for display on the NewRelic RPM website?
     *
     * @var boolean
     */
    protected $explodeArrays;

    /**
     * {@inheritDoc}
     *
     * @param string  $appName
     * @param boolean $explodeArrays
     * @param string  $transactionName
     */
    public function __construct(
        $level = Logger::ERROR,
        $bubble = true,
        $appName = null,
        $explodeArrays = false,
        $transactionName = null
    ) {
        parent::__construct($level, $bubble);

        $this->appName       = $appName;
        $this->explodeArrays = $explodeArrays;
        $this->transactionName = $transactionName;
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        if (!$this->isNewRelicEnabled()) {
            throw new MissingExtensionException('The newrelic PHP extension is required to use the NewRelicHandler');
        }

        if ($appName = $this->getAppName($record['context'])) {
            $this->setNewRelicAppName($appName);
        }

        if ($transactionName = $this->getTransactionName($record['context'])) {
            $this->setNewRelicTransactionName($transactionName);
            unset($record['context']['transaction_name']);
        }

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Exception) {
            newrelic_notice_error($record['message'], $record['context']['exception']);
            unset($record['context']['exception']);
        } else {
            newrelic_notice_error($record['message']);
        }

        foreach ($record['context'] as $key => $parameter) {
            if (is_array($parameter) && $this->explodeArrays) {
                foreach ($parameter as $paramKey => $paramValue) {
                    newrelic_add_custom_parameter('context_' . $key . '_' . $paramKey, $paramValue);
                }
            } else {
                newrelic_add_custom_parameter('context_' . $key, $parameter);
            }
        }

        foreach ($record['extra'] as $key => $parameter) {
            if (is_array($parameter) && $this->explodeArrays) {
                foreach ($parameter as $paramKey => $paramValue) {
                    newrelic_add_custom_parameter('extra_' . $key . '_' . $paramKey, $paramValue);
                }
            } else {
                newrelic_add_custom_parameter('extra_' . $key, $parameter);
            }
        }
    }

    /**
     * Checks whether the NewRelic extension is enabled in the system.
     *
     * @return bool
     */
    protected function isNewRelicEnabled()
    {
        return extension_loaded('newrelic');
    }

    /**
     * Returns the appname where this log should be sent. Each log can override the default appname, set in this
     * handler's constructor, by providing the appname in it's context.
     *
     * @param  array       $context
     * @return null|string
     */
    protected function getAppName(array $context)
    {
        if (isset($context['appname'])) {
            return $context['appname'];
        }

        return $this->appName;
    }

    /**
     * Returns the name of the current transaction. Each log can override the default transaction name, set in this
     * handler's constructor, by providing the transaction_name in it's context
     *
     * @param array $context
     *
     * @return null|string
     */
    protected function getTransactionName(array $context)
    {
        if (isset($context['transaction_name'])) {
            return $context['transaction_name'];
        }

        return $this->transactionName;
    }

    /**
     * Sets the NewRelic application that should receive this log.
     *
     * @param string $appName
     */
    protected function setNewRelicAppName($appName)
    {
        newrelic_set_appname($appName);
    }

    /**
     * Overwrites the name of the current transaction
     *
     * @param $transactionName
     */
    protected function setNewRelicTransactionName($transactionName)
    {
        newrelic_name_transaction($transactionName);
    }
}
