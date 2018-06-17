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
 * Class FluentdFormatter
 *
 * Serializes a log message to Fluentd unix socket protocol
 *
 * Fluentd config:
 *
 * <source>
 *  type unix
 *  path /var/run/td-agent/td-agent.sock
 * </source>
 *
 * Monolog setup:
 *
 * $logger = new Monolog\Logger('fluent.tag');
 * $fluentHandler = new Monolog\Handler\SocketHandler('unix:///var/run/td-agent/td-agent.sock');
 * $fluentHandler->setFormatter(new Monolog\Formatter\FluentdFormatter());
 * $logger->pushHandler($fluentHandler);
 *
 * @author Andrius Putna <fordnox@gmail.com>
 */
class FluentdFormatter implements FormatterInterface
{
    /**
     * @var bool $levelTag should message level be a part of the fluentd tag
     */
    protected $levelTag = false;

    public function __construct(bool $levelTag = false)
    {
        if (!function_exists('json_encode')) {
            throw new \RuntimeException('PHP\'s json extension is required to use Monolog\'s FluentdUnixFormatter');
        }

        $this->levelTag = $levelTag;
    }

    public function isUsingLevelsInTag(): bool
    {
        return $this->levelTag;
    }

    public function format(array $record): string
    {
        $tag = $record['channel'];
        if ($this->levelTag) {
            $tag .= '.' . strtolower($record['level_name']);
        }

        $message = [
            'message' => $record['message'],
            'context' => $record['context'],
            'extra' => $record['extra'],
        ];

        if (!$this->levelTag) {
            $message['level'] = $record['level'];
            $message['level_name'] = $record['level_name'];
        }

        return json_encode([$tag, $record['datetime']->getTimestamp(), $message]);
    }

    public function formatBatch(array $records): string
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }
}
