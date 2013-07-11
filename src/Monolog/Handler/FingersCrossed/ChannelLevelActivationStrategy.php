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
 * Channel and Error level based monolog activation strategy. Allows to trigger activation
 * based on level per channel. e.g. trigger activation on level 'ERROR' by default, except
 * for records of the 'sql' channel; those should trigger activation on level 'WARN'.
 *
 * @author Mike Meessen <netmikey@gmail.com>
 */
class ChannelLevelActivationStrategy implements ActivationStrategyInterface
{
    private $defaultActionLevel;
    private $channelToActionLevel;

    /**
     * @param int $defaultActionLevel The default action level to be used if the record's category doesn't match any
     * @param array $categoryToActionLevel An array that maps channel names to action levels.
     */
    public function __construct($defaultActionLevel, $channelToActionLevel = array())
    {
        $this->defaultActionLevel = $defaultActionLevel;
        $this->channelToActionLevel = $channelToActionLevel;
    }

    public function isHandlerActivated(array $record)
    {
        if (isset($this->channelToActionLevel[$record['channel']])) {
            return $record['level'] >= $this->channelToActionLevel[$record['channel']];
        } else {
            return $record['level'] >= $this->defaultActionLevel;
        }
    }
}
