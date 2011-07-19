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
 * Some methods that are common for all memory processors
 * @author Rob Jensen
 */
abstract class MemoryProcessor extends AbstractProcessor
{

    protected $realUsage;

    /**
     * @param array $options
     */
    public function __construct( $options = array() )
    {
        if(array_key_exists('realUsage', $options )){
            $this->realUsage = (boolean) $options['realUsage'];
        } else {
            $this->realUsage = true;
        }
    }

    /**
     * Formats bytes into a human readable string
     *
     * @param int $bytes
     * @return string
     */
    public static function formatBytes( $bytes )
    {
        $bytes = (int) $bytes;
        if ($bytes > 1024*1024) {
            $bytes = round($bytes/1024/1024, 2).' MB';
        } else if ($bytes > 1024) {
            $bytes = round($bytes/1024, 2).' KB';
        } else {
            $bytes .= ' B';
        }

        return $bytes;
    }

}
