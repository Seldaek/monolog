<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Declared at file scope on purpose: since PHP 8.4 a closure created there is named
// after the file it comes from, which is what the base path has to be stripped from.
return static function (): void {
    throw new \RuntimeException('Thrown from a file scope closure');
};
