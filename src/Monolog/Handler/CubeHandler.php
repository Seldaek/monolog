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
use Monolog\Utils;

/**
 * Logs to Cube.
 *
 * @link https://github.com/square/cube/wiki
 * @author Wan Chen <kami@kamisama.me>
 * @deprecated Since 2.8.0 and 3.2.0, Cube appears abandoned and thus we will drop this handler in Monolog 4
 */
class CubeHandler extends AbstractProcessingHandler
{
    /** @var resource|\Socket|null */
    private $udpConnection = null;
    /** @var resource|\CurlHandle|null */
    private $httpConnection = null;
    /** @var string */
    private $scheme;
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var string[] */
    private $acceptedSchemes = ['http', 'udp'];

    /**
     * Create a Cube handler
     *
     * @throws \UnexpectedValueException when given url is not a valid url.
     *                                   A valid url must consist of three parts : protocol://host:port
     *                                   Only valid protocols used by Cube are http and udp
     */
    public function __construct(string $url, $level = Logger::DEBUG, bool $bubble = true)
    {
        $urlInfo = parse_url($url);

        if ($urlInfo === false || !isset($urlInfo['scheme'], $urlInfo['host'], $urlInfo['port'])) {
            throw new \UnexpectedValueException('URL "'.$url.'" is not valid');
        }

        if (!in_array($urlInfo['scheme'], $this->acceptedSchemes)) {
            throw new \UnexpectedValueException(
                'Invalid protocol (' . $urlInfo['scheme']  . ').'
                . ' Valid options are ' . implode(', ', $this->acceptedSchemes)
            );
        }

        $this->scheme = $urlInfo['scheme'];
        $this->host = $urlInfo['host'];
        $this->port = (int) $urlInfo['port'];

        parent::__construct($level, $bubble);
    }

    /**
     * Establish a connection to an UDP socket
     *
     * @throws \LogicException           when unable to connect to the socket
     * @throws MissingExtensionException when there is no socket extension
     */
    protected function connectUdp(): void
    {
        if (!extension_loaded('sockets')) {
            throw new MissingExtensionException('The sockets extension is required to use udp URLs with the CubeHandler');
        }

        $udpConnection = socket_create(AF_INET, SOCK_DGRAM, 0);
        if (false === $udpConnection) {
            throw new \LogicException('Unable to create a socket');
        }

        $this->udpConnection = $udpConnection;
        if (!socket_connect($this->udpConnection, $this->host, $this->port)) {
            throw new \LogicException('Unable to connect to the socket at ' . $this->host . ':' . $this->port);
        }
    }

    /**
     * Establish a connection to an http server
     *
     * @throws \LogicException           when unable to connect to the socket
     * @throws MissingExtensionException when no curl extension
     */
    protected function connectHttp(): void
    {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is required to use http URLs with the CubeHandler');
        }

        $httpConnection = curl_init('http://'.$this->host.':'.$this->port.'/1.0/event/put');
        if (false === $httpConnection) {
            throw new \LogicException('Unable to connect to ' . $this->host . ':' . $this->port);
        }

        $this->httpConnection = $httpConnection;
        curl_setopt($this->httpConnection, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->httpConnection, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record): void
    {
        $date = $record['datetime'];

        $data = ['time' => $date->format('Y-m-d\TH:i:s.uO')];
        unset($record['datetime']);

        if (isset($record['context']['type'])) {
            $data['type'] = $record['context']['type'];
            unset($record['context']['type']);
        } else {
            $data['type'] = $record['channel'];
        }

        $data['data'] = $record['context'];
        $data['data']['level'] = $record['level'];

        if ($this->scheme === 'http') {
            $this->writeHttp(Utils::jsonEncode($data));
        } else {
            $this->writeUdp(Utils::jsonEncode($data));
        }
    }

    private function writeUdp(string $data): void
    {
        if (!$this->udpConnection) {
            $this->connectUdp();
        }

        socket_send($this->udpConnection, $data, strlen($data), 0);
    }

    private function writeHttp(string $data): void
    {
        if (!$this->httpConnection) {
            $this->connectHttp();
        }

        if (null === $this->httpConnection) {
            throw new \LogicException('No connection could be established');
        }

        curl_setopt($this->httpConnection, CURLOPT_POSTFIELDS, '['.$data.']');
        curl_setopt($this->httpConnection, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen('['.$data.']'),
        ]);

        Curl\Util::execute($this->httpConnection, 5, false);
    }
}
