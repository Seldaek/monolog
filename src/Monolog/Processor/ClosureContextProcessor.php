<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\LogRecord;

/**
 * Generates a context by a closure if the closure is set as the only value
 * in the context
 *
 * It helps to reduce performance impact by debug code
 */
class ClosureContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        if (isset($context[0]) && 1 === \count($context) && $context[0] instanceof \Closure) {
            try {
                $context = $context[0]();
            } catch (\Throwable $e) {
                $context = [
                    'error_on_context_generation' => $e->getMessage(),
                    'exception' => $e,
                ];
            }

            if (!\is_array($context)) {
                $context = [$context];
            }

            $record = $record->with(context: $context);
        }

        return $record;
    }
}
