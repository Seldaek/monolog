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
 * Adds a tags array into record
 *
 * @author Martijn Riemers
 */
class TagProcessor
{
    private $tags;

    public function __construct(array $tags = array())
    {
        $this->tags = $tags;
    }

    public function __invoke(array $record)
    {
        $record['tags'] = array_merge(
            $this->tags,
            $record['tags']
        );

        return $record;
    }
}
