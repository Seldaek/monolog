<?php

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
 * Formats incoming records in order to be a perfect StatsD key.
 *
 * This is especially useful for logging to files
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 * @author Giulio De Donato <liuggio@gmail.com>
 */
class StatsDFormatter extends LineFormatter
{
    const SIMPLE_FORMAT = "%channel%.%level_name%.%short_message%";

    protected $numberOfWords;
    protected $logContext;
    protected $logExtra;

    /**
     * @param  string  $format        The format of the message
     * @param  Boolean $logContext    If true add multiple rows containing Context information
     * @param  Boolean $logExtra      If true add multiple rows containing Extra information
     * @param  integer $numberOfWords The number of words to show.
     */
    public function __construct($format = null, $logContext = true, $logExtra = true, $numberOfWords = 2)
    {
        $this->format = $format ? : static::SIMPLE_FORMAT;
        $this->numberOfWords = $numberOfWords;
        $this->logContext = $logContext;
        $this->logExtra = $logExtra;
        parent::__construct();
    }

    /**
     * This function converts a long message into a string with the first N-words.
     * eg. from: "Notified event "kernel.request" to listener "Symfony\Component\HttpKernel\EventListener"
     *     to:    "Notified event"
     *
     * @param  string $message The message to shortify.
     *
     * @return string
     */
    public function getFirstWords($message)
    {
        $glue = '-';
        $pieces = explode(' ', $message);
        array_splice($pieces, $this->numberOfWords);
        $shortMessage = preg_replace("/[^A-Za-z0-9?![:space:]]/", "-", implode($glue, $pieces));

        return $shortMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $vars = $this->normalize($record);
       
        $firstRow = $this->format;
        $output = array();

        $vars['short_message'] = $this->getFirstWords($vars['message']);
        foreach ($vars as $var => $val) {
            $firstRow = str_replace('%' . $var . '%', $this->convertToString($val), $firstRow);
        }
        $output[] = $firstRow;
        // creating more rows for context content
        if ($this->logContext && isset($vars['context'])) {
            foreach ($vars['context'] as $key => $parameter) {
                $output[] = sprintf("%s.context.%s.%s", $firstRow, $key, $parameter);
            }
        }
        // creating more rows for extra content
        if ($this->logExtra && isset($vars['extra'])) {
            foreach ($vars['extra'] as $key => $parameter) {
                $output[] = sprintf("%s.extra.%s.%s", $firstRow, $key, $parameter);
            }
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        $output = array();
        foreach ($records as $record) {
            $output = array_merge($output, $this->format($record)); 
        }

        return $output;
    }
}
