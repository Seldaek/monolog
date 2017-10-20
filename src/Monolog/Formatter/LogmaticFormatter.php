<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

/**
 * Encodes message information into JSON in a format compatible with Logmatic.
 *
 * @author Julien Breux <julien.breux@gmail.com>
 */
class LogmaticFormatter extends JsonFormatter
{
    const MARKERS = ["sourcecode", "php"];

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
            $record["hostname"] = $this->hostname;
        }
        if (!empty($this->appname)) {
            $record["appname"] = $this->appname;
        }

        $record["@marker"] = self::MARKERS;

        return parent::format($record);
    }
}
