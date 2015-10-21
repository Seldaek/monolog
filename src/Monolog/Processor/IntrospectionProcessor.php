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
 * Injects line/file:class/function where the log message came from
 *
 * Warning: This only works if the handler processes the logs directly.
 * If you put the processor on a handler that is behind a FingersCrossedHandler
 * for example, the processor will only be called once the trigger level is reached,
 * and all the log records will have the same file/line/.. data from the call that
 * triggered the FingersCrossedHandler.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class IntrospectionProcessor
{
    private $level;

    private $skipClassesPartials;

    private $skipFunctions = array(
        'call_user_func',
        'call_user_func_array',
    );

    /**
     * @param array   $record
     * @param integer $extra_stack  -- customize logging of stack trace to extend usability
     * @return array
     */
    public function __invoke(array $record, $extra_stack = 0)
    {
        // return if the level is not high enough
        if ($record['level'] < $this->level) {
            return $record;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // skip first since it's always the current method
        array_shift($trace);
        // the call_user_func call is also skipped
        array_shift($trace);

        $i = 0;

        while ($this->isTraceClassOrSkippedFunction($trace, $i)) {
            if (isset($trace[$i]['class'])) {
                foreach ($this->skipClassesPartials as $part) {
                    if (strpos($trace[$i]['class'], $part) !== false) {
                        $i++;
                        continue 2;
                    }
                }
            } elseif (in_array($trace[$i]['function'], $this->skipFunctions)) {
                $i++;
                continue;
            }

            break;
        }

        // we should have the call source now
        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'file'      => isset($trace[$i + $extra_stack - 1]['file']) ? $trace[$i + $extra_stack - 1]['file'] : null,
                'line'      => isset($trace[$i + $extra_stack - 1]['line']) ? $trace[$i + $extra_stack - 1]['line'] : null,
                'class'     => isset($trace[$i + $extra_stack]['class']) ? $trace[$i + $extra_stack]['class'] : null,
                'function'  => isset($trace[$i + $extra_stack]['function']) ? $trace[$i + $extra_stack]['function'] : null,
            )
        );

        return $record;
    }

    public function __construct($level = Logger::DEBUG, array $skipClassesPartials = array())
    {
        $this->level = Logger::toMonologLevel($level);
        $this->skipClassesPartials = array_merge(array('Monolog\\'), $skipClassesPartials);
    }

    private function isTraceClassOrSkippedFunction(array $trace, $index)
    {
        if (!isset($trace[$index])) {
            return false;
        }

        return isset($trace[$index]['class']) || in_array($trace[$index]['function'], $this->skipFunctions);
    }
}
