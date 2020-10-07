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
use Monolog\Formatter\DatadogFormatter;

/**
 * Inspired on InsightOpsHandler.
 *
 * @see https://docs.datadoghq.com/logs/log_collection/?tab=tcp
 *
 * @author Tristan Bessoussa <tristan.bessoussa@gmail.com>
 */
class DatadogHandler extends SocketHandler
{
    /**
     * @var string
     */
    protected $logToken;

    /**
     * @param string     $apiKey API key supplied by Datadog
     * @param string     $region Region where InsightOps account is hosted. Could be 'us' or 'eu'.
     * @param bool       $useSSL Whether or not SSL encryption should be used
     * @param string|int $level  The minimum logging level to trigger this handler
     * @param bool       $bubble Whether or not messages that are handled should bubble up the stack.
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(string $apiKey, string $region = 'us', bool $useSSL = true, $level = Logger::DEBUG, bool $bubble = true)
    {
        if ($useSSL && !extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP plugin is required to use SSL encrypted connection for DatadogHandler');
        }

        // 'us' is the default region
        $tld = 'com';
        if ('eu' === $region) {
            $tld = 'eu';
        }

        $endpoint = $useSSL
            ? 'ssl://tcp-intake.logs.datadoghq.'.$tld.':443'
            : 'tcp-intake.logs.datadoghq.'.$tld.':1883';

        parent::__construct($endpoint, $level, $bubble);
        $this->logToken = $apiKey;
    }

    protected function generateDataStream(array $record): string
    {
        return $this->logToken . ' ' . $record['formatted'];
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new DatadogFormatter();
    }
}
