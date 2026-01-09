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

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Utils;

/**
 * Sends logs to a generic webhook URL using HTTP(S) POST requests with a JSON payload
 *
 * @author Raphael Portmann <github@i.raphaelportmann.com>
 */
class WebhookHandler extends SocketHandler
{
    /**
     * @var array Webhook URL components
     */
    protected array $url;

    /**
     * Construct a new Webhook Handler.
     *
     * @param  string                    $url Webhook URL
     * @throws \InvalidArgumentException if the provided URL is not valid
     * @throws MissingExtensionException if OpenSSL is missing for HTTPS URLs
     */
    public function __construct(
        string $url,
        $level = Level::Debug,
        bool $bubble = true,
        bool $persistent = false,
        float $timeout = 0.0,
        float $writingTimeout = 10.0,
        ?float $connectionTimeout = null,
        ?int $chunkSize = null
    ) {
        $this->url = parse_url($url);

        if ($this->url === false || !isset($this->url['scheme'], $this->url['host'])) {
            throw new \InvalidArgumentException('The provided URL is not valid.');
        }

        $isSSL = $this->url['scheme'] === 'https';

        if ($isSSL && !\extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required when using an HTTPS webhook URL.');
        }

        $connectionString = ($isSSL ? 'ssl://' : 'tcp://') . $this->url['host'] . ':' . ($this->url['port'] ?? ($isSSL ? 443 : 80));
        parent::__construct(
            $connectionString,
            $level,
            $bubble,
            $persistent,
            $timeout,
            $writingTimeout,
            $connectionTimeout,
            $chunkSize
        );
    }

    /**
     * Handles a log record
     */
    public function write(LogRecord $record): void
    {
        parent::write($record);
        $this->closeSocket();
    }

    /**
     * @inheritDoc
     */
    protected function generateDataStream(LogRecord $record): string
    {
        $content = $this->buildContent($record);

        return $this->buildHeader($content) . $content;
    }

    /**
     * Builds the header of the API Call
     */
    private function buildHeader(string $content): string
    {
        $header = "POST " . ($this->url['path'] ?? '/') . " HTTP/1.1\r\n";
        $header .= "Host: " . $this->url['host'] . "\r\n";
        $header .= "Content-Type: application/json\r\n";
        $header .= "Content-Length: " . \strlen($content) . "\r\n";
        $header .= "\r\n";

        return $header;
    }

    /**
     * Builds the body of API call
     */
    private function buildContent(LogRecord $record): string
    {
        return Utils::jsonEncode($record->toArray());
    }
}
