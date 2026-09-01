<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require '/app/source/vendor/autoload.php';

use Monolog\Handler\FrankenPhpHandler;
use Monolog\Level;
use Monolog\Logger;

$logger = new Logger('e2e');
$logger->pushHandler(new FrankenPhpHandler(Level::Debug));
$logger->warning('Hello from Monolog via FrankenPHP', ['foo' => 'bar']);
$logger->critical('Custom FrankenPHP level');

echo 'OK';
