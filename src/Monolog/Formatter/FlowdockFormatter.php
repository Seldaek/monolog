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
 * formats the record to be used in the FlowdockHandler
 *
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 */
class FlowdockFormatter implements FormatterInterface
{
    /**
     * @var string
     */
    private $source;

    /**
     * @var string
     */
    private $sourceEmail;

    public function __construct(string $source, string $sourceEmail)
    {
        $this->source = $source;
        $this->sourceEmail = $sourceEmail;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record): array
    {
        $tags = [
            '#logs',
            '#' . strtolower($record['level_name']),
            '#' . $record['channel'],
        ];

        foreach ($record['extra'] as $value) {
            $tags[] = '#' . $value;
        }

        $subject = sprintf(
            'in %s: %s - %s',
            $this->source,
            $record['level_name'],
            $this->getShortMessage($record['message'])
        );

        $record['flowdock'] = [
            'source' => $this->source,
            'from_address' => $this->sourceEmail,
            'subject' => $subject,
            'content' => $record['message'],
            'tags' => $tags,
            'project' => $this->source,
        ];

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records): array
    {
        $formatted = [];

        foreach ($records as $record) {
            $formatted[] = $this->format($record);
        }

        return $formatted;
    }

    public function getShortMessage(string $message): string
    {
        static $hasMbString;

        if (null === $hasMbString) {
            $hasMbString = function_exists('mb_strlen');
        }

        $maxLength = 45;

        if ($hasMbString) {
            if (mb_strlen($message, 'UTF-8') > $maxLength) {
                $message = mb_substr($message, 0, $maxLength - 4, 'UTF-8') . ' ...';
            }
        } else {
            if (strlen($message) > $maxLength) {
                $message = substr($message, 0, $maxLength - 4) . ' ...';
            }
        }

        return $message;
    }
}
