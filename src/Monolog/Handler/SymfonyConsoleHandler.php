<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

/**
 * Hadnler sending logs to Symfony/Console output
 *
 * @author Vitaliy Zhuk <zhuk2205@gmail.com>
 */
class SymfonyConsoleHandler extends AbstractProcessingHandler
{
    /**
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $consoleOutput;

    /**
     * @var array
     */
    protected $levelStyles = array(
        Logger::DEBUG       =>  'debug',
        Logger::NOTICE      =>  'notice',
        Logger::INFO        =>  'info',
        Logger::WARNING     =>  'warning',
        Logger::ERROR       =>  'error',
        Logger::CRITICAL    =>  array('critical', 'error'),
        Logger::ALERT       =>  array('alert', 'error'),
        Logger::EMERGENCY   =>  array('emergency', 'error')
    );

    /**
     * Construct
     *
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @param integer $level
     */
    public function __construct(OutputInterface $output, $level = Logger::DEBUG)
    {
        $this->consoleOutput = $output;
        parent::__construct($level);
    }

    /**
     * Set level style
     *
     * @param itneger $level
     * @param string|array $style
     */
    public function setLevelStyle($level, $style)
    {
        try {
            Logger::getLevelName($level);
        }
        catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(sprintf(
                'Can\'t set style "%s" to error level "%s".',
                $style, $level
            ), 0, $exception);
        }

        $this->levelStyles[$level] = $style;

        return $this;
    }

    /**
     * Get level style
     *
     * @param integer $level
     * @return string|array
     */
    public function getLevelStyle($level)
    {
        if (!isset($this->levelStyles[$level])) {
            throw new \InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys($this->levelStyles)));
        }

        return $this->levelStyles[$level];
    }

    /**
     * @{inerhitDoc}
     */
    public function write(array $record)
    {
        $writeText = $record['formatted'];

        // Check usage formatter
        $formatter = $this->consoleOutput->getFormatter();
        if ($formatter && $formatter->format($writeText) == $writeText) {
            $levelStyle = $this->levelStyles[$record['level']];

            if (is_string($levelStyle)) {
                if ($formatter->hasStyle($levelStyle)) {
                    $writeText = '<' . $levelStyle . '>' . $writeText . '</' . $levelStyle . '>';
                }
            }
            else if (is_array($levelStyle) || $levelStyle instanceof \Iterator) {
                foreach ($levelStyle as $style) {
                    if ($formatter->hasStyle($style)) {
                        $writeText = '<' . $style . '>' . $writeText . '</' . $style . '>';
                        break;
                    }
                }
            }
        }

        $this->consoleOutput->writeln($writeText);
    }

    /**
     * @{inerhitDoc}
     */
    public function getDefaultFormatter()
    {
        return new LineFormatter("%message%");
    }
}