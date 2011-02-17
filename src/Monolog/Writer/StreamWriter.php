<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Writer;

use Monolog\Formatter\FormatterInterface;

class StreamWriter implements WriterInterface
{
    protected $formatter;
    protected $stream;
    protected $url;

    public function __construct($streamUrl)
    {
        if (is_resource($streamUrl)) {
            $this->stream = $streamUrl;
        } else {
            $this->url = $streamUrl;
        }
    }

    public function write($log, $level, $message)
    {
        if (null === $this->stream) {
            $this->stream = fopen($this->url, 'a');
        }
        fwrite($this->stream, $this->formatter->format($log, $level, $message));
    }

    public function close()
    {
        fclose($this->stream);
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }
}