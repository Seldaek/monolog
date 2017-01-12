<?php declare(strict_types = 1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

const APP_FIELD_NAME = "appname";
const HOST_FIELD_NAME = "hostname";
const MARKER_FIELD_NAME = "@marker";

/**
 * Encodes message information into JSON in a format compatible with Logmatic.
 *
 * @author Julien Breux <julien.breux@gmail.com>
 */
class LogmaticFormatter extends JsonFormatter
{
    /**
     * @param array $marker Used by Logmatic.io to identify the technology
     */
    protected $marker = ["sourcecode", "php"];

    /**
     * @param string
     */
    protected $hostname = '';

    /**
     * @param string
     */
    protected $appname = '';

    /**
     * Set hostname
     *
     * @param string $hostname
     */
    public function setHostname(string $hostname)
    {
        $this->hostname = $hostname;
    }

    /**
     * Set appname
     *
     * @param string $appname
     */
    public function setAppname(string $appname)
    {
        $this->appname = $appname;
    }

    /**
     * Appends the 'hostname' and 'appname' parameter for indexing by Logmatic.
     *
     * @see http://doc.logmatic.io/docs/basics-to-send-data
     * @see \Monolog\Formatter\JsonFormatter::format()
     */
    public function format(array $record): string
    {
        if (!empty($this->hostname)) {
            $record[HOST_FIELD_NAME] = $this->hostname;
        }
        if (!empty($this->appname)) {
            $record[APP_FIELD_NAME] = $this->appname;
        }

        $record[MARKER_FIELD_NAME] = $this->marker;

        return parent::format($record);
    }
}
