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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SapiProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['SAPI'] = \PHP_SAPI;

        if (\function_exists('cli_get_process_title') && "" !== ($title = @cli_get_process_title())) {
            $record->extra['process_title'] = $title;
        }

        return $record;
    }
}
