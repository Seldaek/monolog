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
 * Adds a unique identifier into records
 *
 * @author Simon Mönch <sm@webfactory.de>
 */
class UidProcessor 
{
    private $uid;
    
    public function __construct() 
    {
        if (null === $this->uid) {
            $this->uid = substr(hash('md5', uniqid('', true)), 0, 7);
        }
    }

    public function __invoke(array $record) 
    {
        $record['extra']['uid'] = $this->uid;

        return $record;
    }
}