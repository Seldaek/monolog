<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\Logger;

/**
 * Pass through whatever record data is passed to it
 *
 * This is used primarily for the FirePHPHandler
 *
 * @author Christoph Dorn <christoph@christophdorn.com>
 */
class PassthruFormatter implements FormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        return $records;
    }
}
