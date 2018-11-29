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
    protected const MARKERS = ["sourcecode", "php"];

    /**
     * @var string
     */
    protected $hostname = '';

    /**
     * @var string
     */
    protected $appname = '';

    public function setHostname(string $hostname): self
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function setAppname(string $appname): self
    {
        $this->appname = $appname;

        return $this;
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

        $record["@marker"] = static::MARKERS;

        return parent::format($record);
    }
}
