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

/**
 * Extends the standard JsonFormatter to format messages compatible with Loggly.
 *
 * @author Adam Pancutt <adam@pancutt.com>
 */
class LogglyFormatter extends JsonFormatter
{

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        $instance = $this;

        array_walk($records, function(&$value, $key) use ($instance) {
            $value = $instance->format($value);
        });

        return implode("\n", $records);
    }

}
