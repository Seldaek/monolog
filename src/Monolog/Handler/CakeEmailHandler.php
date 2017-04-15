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
use Monolog\Handler\MailHandler;

/**
 * CakeEmailHandler uses CakeEmail to send the emails.
 *
 * @author Jad Bitar <jadbitar@mac.com>
 */
class CakeEmailHandler extends MailHandler
{

    protected $to;
    protected $subject;
    protected $config;

    /**
     * @param string|array $to      The receiver of the mail
     * @param string       $subject The subject of the mail
     * @param string       $from    The CakeEmail configuration to use
     * @param integer      $level   The minimum logging level at which this handler will be triggered
     * @param boolean      $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($to, $subject, $config = 'default', $level = Logger::ERROR, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->to = $to;
        $this->subject = $subject;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function send($content, array $records)
    {
        \CakeEmail::deliver($this->to, $this->subject, $content, $this->config);
    }

}
