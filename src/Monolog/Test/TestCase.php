<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Test;

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\JsonSerializableDateTimeImmutable;
use Monolog\Formatter\FormatterInterface;
use Psr\Log\LogLevel;
use ReflectionProperty;

/**
 * Lets you easily generate log records and a dummy formatter for testing purposes
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @deprecated use MonologTestCase instead.
 */
class TestCase extends MonologTestCase
{
}
