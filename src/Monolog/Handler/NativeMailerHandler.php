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

use Monolog\Logger;

/**
 * NativeMailerHandler uses the mail() function to send the emails
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class NativeMailerHandler extends MailHandler
{
    protected $to;
    protected $subject;
    protected $headers = array(
        'Content-type: text/plain; charset=utf-8'
    );

    /**
     * @param string|array $to      The receiver of the mail
     * @param string       $subject The subject of the mail
     * @param string       $from    The sender of the mail
     * @param integer      $level   The minimum logging level at which this handler will be triggered
     * @param boolean      $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($to, $subject, $from, $level = Logger::ERROR, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->to = is_array($to) ? $to : array($to);
        $this->subject = $subject;
        $this->headers[] = sprintf('From: %s', $from);
    }

    /**
     * @param string|array $header Custom added headers
     */
    public function addHeader($headers)
    {
        if (is_array($headers)) {
            $this->headers = array_merge($this->headers, $headers);
        } else {
            $this->headers[] = $headers;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content, array $records)
    {
        foreach ($this->to as $to) {
            mail($to, $this->subject, wordwrap($content, 70), implode("\r\n", $this->headers) . "\r\n");
        }
    }
}
