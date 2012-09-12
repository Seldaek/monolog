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
 * Injects several EC2 instance properties in all records
 *
 * @author Clay Loveless <clay@php.net>
 */
class AmazonEC2Processor
{
    /**
     * @see http://docs.amazonwebservices.com/AWSEC2/latest/UserGuide/AESDG-chapter-instancedata.html
     */
    protected $metadataKeys = array(
        'ami-id',
        'hostname',
        'instance-id',
        'instance-type',
        'local-ipv4',
        'public-ipv4',
        'placement/availability-zone'
    );

    protected $baseUrl;

    /**
     * @param array   $metadataKeys Array of metadata keys to record
     * @param boolean $overwrite    If TRUE, passed metadataKeys will not be merged
     * @param string  $baseUrl      Used to set where the metadata should be queried
     */
    public function __construct(array $metadataKeys, $overwrite = false, $baseUrl = 'http://169.254.169.254')
    {
        if ($overwrite) {
            $this->metadataKeys = $metadataKeys;
        } else {
            $this->metadataKeys = array_merge($this->metadataKeys, $metadataKeys);
        }
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $metadata = array();

        // should be an instant connection if on EC2, so timeout after
        // one second to minimize delay for anyone using this processor
        // mistakenly in a local dev environment.
        $socketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 1);

        // if we can't get hostname, we can't get any others either
        $hostname = file_get_contents($this->baseUrl . '/latest/meta-data/hostname');
        if ($hostname !== false) {
            // don't get hostname twice
            if (in_array('hostname', $this->metadataKeys)) {
                $metadata['hostname'] = $hostname;
            }
            foreach ($this->metadataKeys as $metakey) {
                if ($metakey == 'hostname') {
                    continue;
                }
                $url = $this->baseUrl . '/latest/meta-data/' . $metakey;
                $metadata[$metakey] = file_get_contents($url);
            }
        }

        // restore socket settings
        ini_set('default_socket_timeout', $socketTimeout);

        $record['extra'] = array_merge(
            $record['extra'],
            $metadata
        );

        return $record;
    }
}
