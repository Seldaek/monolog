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
 * Stores logs to files that are rotated every n day/week/month
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class RotatingFileHandler extends StreamHandler
{
    protected $rotation;
    protected $maxAge;

    public function close()
    {
        parent::close();
        // TODO rotation
    }

    public function setRotation($rotation)
    {
        $this->rotation = $rotation;
    }

    public function setMaxAge($maxAge)
    {
        $this->maxAge = $maxAge;
    }
}