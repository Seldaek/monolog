<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\FingersCrossed;

/**
 * Error level based activation strategy.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ErrorLevelActivationStrategy
    implements ActivationStrategyInterface
{
    /**
     * @var string|integer
     */
    private $actionLevel;

    /**
     * @param string|integer $actionLevel
     */
    public function __construct($actionLevel)
    {
        $this->actionLevel = $actionLevel;
    }

    /**
     * @param array $record
     *
     * @return bool
     */
    public function isHandlerActivated(array $record)
    {
        return $record['level'] >= $this->actionLevel;
    }
}
