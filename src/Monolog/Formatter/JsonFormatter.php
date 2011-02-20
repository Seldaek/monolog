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

class JsonFormatter implements FormatterInterface
{
    public function format($message)
    {
        $message['message'] = json_encode($message['message']);
        return $message;
    }
}
