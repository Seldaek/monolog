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

/**
 * Inspired on LogEntriesHandler.
 *
 * @author Robert Kaufmann III <rok3@rok3.me>
 * @author Gabriel Machado <gabriel.ms1@hotmail.com>
 */
class InsightOpsHandler extends SocketHandler
{
    /**
     * @var string
     */
    protected $logToken;

    /**
     * @param string     $token  Log token supplied by InsightOps
     * @param string     $region Region where InsightOps account is hosted. Could be 'us' or 'eu'.
     * @param bool       $useSSL Whether or not SSL encryption should be used
     * @param string|int $level  The minimum logging level to trigger this handler
     * @param bool       $bubble Whether or not messages that are handled should bubble up the stack.
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(string $token, string $region = 'us', bool $useSSL = true, $level = Logger::DEBUG, bool $bubble = true)
    {
        if ($useSSL && !extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP plugin is required to use SSL encrypted connection for LogEntriesHandler');
        }

        $endpoint = $useSSL
            ? 'ssl://' . $region . '.data.logs.insight.rapid7.com:443'
            : $region . '.data.logs.insight.rapid7.com:80';

        parent::__construct($endpoint, $level, $bubble);
        $this->logToken = $token;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataStream(array $record): string
    {
        return $this->logToken . ' ' . $record['formatted'];
    }
}
