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
    const LOGMATIC_URI = '/v1/';

    /**
     * @var string
     */
    protected $logToken;

    /**
     * @var string
     */
    protected $hostname;

    /**
     * @var string
     */
    protected $appname;

    /**
     * @param string $hostname  Host name supplied by Logmatic.
     * @param string $appname   Application name supplied by Logmatic.
     * @param string $token     Log token supplied by Logmatic.
     * @param bool   $useSSL    Whether or not SSL encryption should be used.
     * @param int    $level     The minimum logging level to trigger this handler.
     * @param bool   $bubble    Whether or not messages that are handled should bubble up the stack.
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct($token, $hostname = '', $appname = '', $useSSL = true, $level = Logger::DEBUG, $bubble = true)
    {
        if ($useSSL && !extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP plugin is required to use SSL encrypted connection for LogmaticHandler');
        }

        $endpoint = $useSSL ? 'ssl://api.logmatic.io:10515' : 'api.logmatic.io:10514';

        parent::__construct($endpoint . self::LOGMATIC_URI, $level, $bubble);

        $this->logToken = $token;
        $this->hostname = $hostname;
        $this->appname  = $appname;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataStream($record): String
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
