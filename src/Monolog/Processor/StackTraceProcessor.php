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

use Monolog\Logger;

/**
 * Injects a stack trace
 *
 * Warning: This only works if the handler processes the logs directly.
 * If you put the processor on a handler that is behind a FingersCrossedHandler
 * for example, the processor will only be called once the trigger level is reached,
 * and all the log records will have the same file/line/.. data from the call that
 * triggered the FingersCrossedHandler.
 *
 * @author Brian Cappello <brian@vtdesignworks.com>
 */
class StackTraceProcessor extends IntrospectionProcessor
{
    /**
     * {@inheritDoc}
     */
    protected function getExtras(array $trace, $i)
    {
        $e = isset($trace[$i]['args'][0]) ? $trace[$i]['args'][0] : null;

        return array(
            'stacktrace' => $e instanceof \Exception ? $e->getTraceAsString() : '',
        );
    }
}
