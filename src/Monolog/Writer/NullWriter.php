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

use Monolog\Formatter\FormatterInterface;

class NullWriter implements WriterInterface
{
    public function write($log, $level, $message)
    {
    }

    public function close()
    {
    }

    public function setFormatter(FormatterInterface $formatter)
    {
    }
}