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

class NullHandler extends AbstractHandler
{
    public function handle($message)
    {
        if ($message['level'] < $this->level) {
            return false;
        }
        return false === $this->bubble;
    }

    public function write($message)
    {
    }
}