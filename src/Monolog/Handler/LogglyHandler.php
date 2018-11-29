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
use Monolog\Formatter\LogglyFormatter;

/**
 * Sends errors to Loggly.
 *
 * @author Przemek Sobstel <przemek@sobstel.org>
 * @author Adam Pancutt <adam@pancutt.com>
 * @author Gregory Barchard <gregory@barchard.net>
 */
class LogglyHandler extends AbstractProcessingHandler
{
    protected const HOST = 'logs-01.loggly.com';
    protected const ENDPOINT_SINGLE = 'inputs';
    protected const ENDPOINT_BATCH = 'bulk';

    protected $token;

    protected $tag = [];

    /**
     * @param string     $token  API token supplied by Loggly
     * @param string|int $level  The minimum logging level to trigger this handler
     * @param bool       $bubble Whether or not messages that are handled should bubble up the stack.
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(string $token, $level = Logger::DEBUG, bool $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the LogglyHandler');
        }

        $this->token = $token;

        parent::__construct($level, $bubble);
    }

    /**
     * @param string[]|string $tag
     */
    public function setTag($tag): self
    {
        $tag = !empty($tag) ? $tag : [];
        $this->tag = is_array($tag) ? $tag : [$tag];

        return $this;
    }

    /**
     * @param string[]|string $tag
     */
    public function addTag($tag): self
    {
        if (!empty($tag)) {
            $tag = is_array($tag) ? $tag : [$tag];
            $this->tag = array_unique(array_merge($this->tag, $tag));
        }

        return $this;
    }

    protected function write(array $record): void
    {
        $this->send($record["formatted"], static::ENDPOINT_SINGLE);
    }

    public function handleBatch(array $records): void
    {
        $level = $this->level;

        $records = array_filter($records, function ($record) use ($level) {
            return ($record['level'] >= $level);
        });

        if ($records) {
            $this->send($this->getFormatter()->formatBatch($records), static::ENDPOINT_BATCH);
        }
    }

    protected function send(string $data, string $endpoint): void
    {
        $url = sprintf("https://%s/%s/%s/", static::HOST, $endpoint, $this->token);

        $headers = ['Content-Type: application/json'];

        if (!empty($this->tag)) {
            $headers[] = 'X-LOGGLY-TAG: '.implode(',', $this->tag);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        Curl\Util::execute($ch);
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LogglyFormatter();
    }
}
