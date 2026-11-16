<?php declare(strict_types=1);

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
 * Interface for formatters decorating another formatter
 *
 * Handlers which only accept one specific formatter can use Utils::unwrapFormatter()
 * to see through the decoration and accept a wrapped instance of that formatter.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface WrappingFormatterInterface extends FormatterInterface
{
    /**
     * Returns the formatter this one delegates the actual formatting to
     */
    public function getWrappedFormatter(): FormatterInterface;
}
