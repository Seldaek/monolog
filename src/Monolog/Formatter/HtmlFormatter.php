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

use Monolog\Logger;

/**
 * Formats incoming records into an HTML table
 * This is especially useful for html email logging
 *
 * @author Tiago Brito <tlfbrito@gmail.com>
 */
class HtmlFormatter
    extends NormalizerFormatter
{
    /**
     * Translates Monolog log levels to html color priorities.
     */
    private $logLevels
        = array(
            Logger::DEBUG     => '#cccccc',
            Logger::INFO      => '#468847',
            Logger::NOTICE    => '#3a87ad',
            Logger::WARNING   => '#c09853',
            Logger::ERROR     => '#f0ad4e',
            Logger::CRITICAL  => '#b94a48',
            Logger::ALERT     => '#d9534f',
            Logger::EMERGENCY => '#ffffff',
        );

    /**
     * @param string $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct($dateFormat = null)
    {
        parent::__construct($dateFormat);
    }

    /**
     * Creates an HTML table row
     *
     * @param string $th Row header content
     * @param string $td Row standard cell content
     *
     * @return string
     */
    private function addRow($th,
                            $td = ' ')
    {
        $th = htmlspecialchars(
            $th,
            ENT_NOQUOTES,
            'UTF-8'
        );
        $td = '<pre>' . htmlspecialchars(
                $td,
                ENT_NOQUOTES,
                'UTF-8'
            ) . '</pre>';

        return '<tr style="padding: 4px;spacing: 0;text-align: left;">' . "\n"
        . '<th style="background: #cccccc" width="100px">' . $th . ':</th>' . "\n"
        . '<td style="padding: 4px;spacing: 0;text-align: left;background: #eeeeee">' . $td . '</td>' . "\n"
        . '</tr>';
    }

    /**
     * Create a HTML h1 tag
     *
     * @param string  $title Text to be in the h1
     * @param integer $level Error level
     *
     * @return string
     */
    private function addTitle($title,
                              $level)
    {
        $title = htmlspecialchars(
            $title,
            ENT_NOQUOTES,
            'UTF-8'
        );

        return
            '<h1 style="background: ' . $this->logLevels[$level] . ';color: #ffffff;padding: 5px;">' . $title . '</h1>';
    }

    /**
     * Formats a log record.
     *
     * @param array $record A record to format
     *
     * @return mixed The formatted record
     */
    public function format(array $record)
    {
        $output = $this->addTitle(
            $record['level_name'],
            $record['level']
        );
        $output .= '<table cellspacing="1" width="100%">';

        $output .= $this->addRow(
            'Message',
            (string) $record['message']
        );
        $output .= $this->addRow(
            'Time',
            $record['datetime']->format('Y-m-d\TH:i:s.uO')
        );
        $output .= $this->addRow(
            'Channel',
            $record['channel']
        );
        if ($record['context']) {
            $output .= $this->addRow(
                'Context',
                $this->convertToString($record['context'])
            );
        }
        if ($record['extra']) {
            $output .= $this->addRow(
                'Extra',
                $this->convertToString($record['extra'])
            );
        }

        return $output . '</table>';
    }

    /**
     * Formats a set of log records.
     *
     * @param array $records A set of records to format
     *
     * @return mixed The formatted set of records
     */
    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    /**
     * @param $data
     *
     * @return mixed|string
     */
    protected function convertToString($data)
    {
        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        $data = $this->normalize($data);
        if (version_compare(
            PHP_VERSION,
            '5.4.0',
            '>='
        )
        ) {
            return json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        return str_replace(
            '\\/',
            '/',
            json_encode($data)
        );
    }
}
