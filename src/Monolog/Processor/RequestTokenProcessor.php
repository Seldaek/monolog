<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

/**
 * Adds a request token into records
 *
 * @author Simon Mönch <sm@webfactory.de>
 */
class RequestTokenProcessor 
{
    private static $requestToken;
    
    public function __construct() 
    {
        if (null === self::$requestToken) {
            self::$requestToken = substr(hash('md5', uniqid('', true)), 0, 7);
        }
    }

    public function __invoke(array $record) 
    {
        $record['extra']['request_token'] = self::$requestToken;

        return $record;
    }
}