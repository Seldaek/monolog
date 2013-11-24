<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

use InvalidArgumentException;

/**
 * Monolog log registry
 *
 * Allows to get `Logger` instances in the global scope
 * via static method calls on this class.
 *
 * <code>
 * $application = new Monolog\Logger('application');
 * $api = new Monolog\Logger('api');
 *
 * Monolog\Registry::addLogger($application);
 * Monolog\Registry::addLogger($api);
 *
 * function testLogger()
 * {
 *     Monolog\Registry::api()->addError('Sent to $api Logger instance');
 *     Monolog\Registry::application()->addError('Sent to $application Logger instance');
 * }
 * </code>
 *
 * @author Tomas Tatarko <tomas@tatarko.sk>
 */
class Registry
{
    /**
     * List of all loggers in the registry (ba named indexes)
     * @var array of Logger
     */
    protected static $loggers = array();

    /**
     * Adds new logging channel to the registry
     * @param Logger $logger Instance of the logging channel
     * @param string $name Name of the logging channel ($logger->getName() by default)
     * @param boolean $overwrite Overwrite instance in the registry if the given name already exists?
     * @throws \InvalidArgumentException If $overwrite set to false and named Logger instance already exists
     */
    public static function addLogger(Logger $logger, $name = null, $overwrite = false)
    {
        $name = $name ? : $logger->getName();

        if (isset(self::$loggers[$name]) && !$overwrite) {
            throw new InvalidArgumentException('Logger with the given name already exists');
        }

        self::$loggers[$name] = $logger;
    }

    /**
     * Removes instance from registry by the given name
     * @param string $name Named index to remove
     */
    public static function removeLoggerByName($name)
    {
        unset(self::$loggers[$name]);
    }

    /**
     * Removes instance from registry by the given instance
     * @param Logger $instance Instance thats pointer should be removed from the registry
     */
    public static function removeLoggerByInstance(Logger $instance)
    {
        foreach (self::$loggers as $key => $logger) {
            if ($logger === $instance) {
                self::removeLoggerByName($key);
            }
        }
    }

    /**
     * Clears the registry
     */
    public static function clear()
    {
        self::$loggers = array();
    }

    /**
     * Gets Logger instance from the registry
     * @param string $name Name of the requested Logger instance
     * @return Logger Requested instance of Logger
     * @throws \InvalidArgumentException If named Logger instance is not in the registry
     */
    public static function getInstance($name)
    {
        if (!isset(self::$loggers[$name])) {
            throw new InvalidArgumentException(sprintf('Requested "%s" logger instance is not in the registry', $name));
        }

        return self::$loggers[$name];
    }

    /**
     * Gets Logger instance from the registry via static method call
     * @param string $name Name of the requested Logger instance
     * @param array $arguments Arguments passed to static method call
     * @return Logger Requested instance of Logger
     * @throws \InvalidArgumentException If named Logger instance is not in the registry
     */
    public static function __callStatic($name, $arguments)
    {
        return self::getInstance($name);
    }
}