<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

/**
 * Handler or Processor implementing this interface will be reset when Logger::reset() is called.
 *
 * Resetting an Handler or a Processor usually means cleaning all buffers or
 * resetting in its internal state. This should also generally close() the handler.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface ResettableInterface
{
    public function reset();
}
