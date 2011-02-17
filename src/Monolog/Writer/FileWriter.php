<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Writer;

class FileWriter extends StreamWriter
{
    protected $rotation;
    protected $maxAge;

    public function __construct($file, $rotation = null, $maxAge = null)
    {
        parent::__construct($file);
        $this->rotation = $rotation;
        $this->maxAge = $maxAge;
    }

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