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

/**
 * Basic processor class
 * @author Rob Jensen
 */
abstract class AbstractProcessor
{

    abstract public function __invoke( array $record );

}
