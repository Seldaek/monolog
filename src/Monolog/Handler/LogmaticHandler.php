<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LogmaticFormatter;

/**
 * @author Julien Breux <julien.breux@gmail.com>
 */
class LogmaticHandler extends SocketHandler
{
    /**
     * @var string
     */
    private $logToken;

    /**
     * @var string
     */
    private $hostname;

    /**
     * @var string
     */
    private $appname;

    /**
     * @param string     $token    Log token supplied by Logmatic.
     * @param string     $hostname Host name supplied by Logmatic.
     * @param string     $appname  Application name supplied by Logmatic.
     * @param bool       $useSSL   Whether or not SSL encryption should be used.
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(string $token, string $hostname = '', string $appname = '', bool $useSSL = true, $level = Logger::DEBUG, bool $bubble = true)
    {
        if ($useSSL && !extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required to use SSL encrypted connection for LogmaticHandler');
        }

        $endpoint = $useSSL ? 'ssl://api.logmatic.io:10515' : 'api.logmatic.io:10514';
        $endpoint .= '/v1/';

        parent::__construct($endpoint, $level, $bubble);

        $this->logToken = $token;
        $this->hostname = $hostname;
        $this->appname  = $appname;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataStream(array $record): string
    {
        return $this->logToken . ' ' . $record['formatted'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        $formatter = new LogmaticFormatter();

        if (!empty($this->hostname)) {
            $formatter->setHostname($this->hostname);
        }
        if (!empty($this->appname)) {
            $formatter->setAppname($this->appname);
        }

        return $formatter;
    }
}
