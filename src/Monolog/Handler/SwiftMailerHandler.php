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

/**
 * SwiftMailerHandler uses Swift_Mailer to send the emails
 * 
 * @author Gyula Sallai
 */
class SwiftMailerHandler extends MailHandler
{
    
    protected $mailer;
    protected $message;
    
    /**
     * @param \Swift_Mailer $mailer The mailer to use
     * @param \Swift_Message $message An example message for real messages,
     *                                only the body will be replaced
     */
    public function __construct(\Swift_Mailer $mailer, \Swift_Message $message)
    {
        $this->mailer  = $mailer;
        $this->message = $message;
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content)
    {
        $message = clone $this->message;
        $message->setBody($content);
        
        $this->mailer->send($message);
    }
    
}