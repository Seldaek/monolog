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
    protected $headers;

    /**
     * @param string $to The receiver of the mail
     * @param string $subject The subject of the mail
     * @param string $from The sender of the mail
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($to, $subject, $from, $level = Logger::ERROR, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->to = $to;
        $this->subject = $subject;
        $this->headers = sprintf("From: %s\r\nContent-type: text/plain; charset=utf-8\r\n", $from);
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content)
    {
        mail($this->to, $this->subject, wordwrap($content, 70), $this->headers);
    }
}