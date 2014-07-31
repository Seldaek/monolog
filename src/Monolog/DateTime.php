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

/**
 * Add microsecond detail to JSON encoded DateTime objects
 *
 * @author Menno Holtkamp
 */
class DateTime extends \DateTime implements \JsonSerializable
{

    /**
     * Append the microseconds to the date property
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $result = (array)$this;
        $result['date'] .= $this->format('.u');

        return $result;
    }

}
