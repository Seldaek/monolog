<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\InsightHandler;

/**
 * Swallows all Insight API calls if Insight not loaded.
 *
 * @author Christoph Dorn (@cadorn) <christoph@christophdorn.com>
 */
class NullMessage
{
    protected static $instance = null;
    public static function getInstance() {
        if(!self::$instance) {
            self::$instance = new NullMessage();
        }
        return self::$instance;
    }
    public static function to() {
        return self::getInstance();
    }
    public static function plugin() {
        return self::getInstance();
    }
    public function __call($name, $arguments) {
        return self::getInstance();
    }
    public static function __callStatic($name, $arguments) {
        return self::getInstance();
    }
}