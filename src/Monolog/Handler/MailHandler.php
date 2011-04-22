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
 * Base class for all mail handlers
 * 
 * @author Gyula Sallai
 */
abstract class MailHandler extends AbstractHandler
{
    
    protected $messageFormat;
    
    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if ($record['level'] < $this->level) {
            return false;
        }
        
        $record = $this->prepareRecord($record);
        
        $this->write($record);
        
        return false === $this->bubble;
    }
    
    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {   
        $messages = array();
        
        foreach ($records as $record) {
            if ($record['level'] < $this->level) {
                continue;
            }
            
            $record = $this->prepareRecord($record);
            $messages[] = $record['message'];
        }
        
        $this->send($this->createMessage($messages));
    }
    
    /**
     * Set the message format
     * 
     * @param string $format
     */
    public function setMessageFormat($format)
    {
        $this->messageFormat = $format;
    }
    
    /**
     * Get the message format
     * 
     * @return string
     */
    public function getMessageFormat()
    {
        return $this->messageFormat;
    }
    
    /**
     * Create a message to send from the given log entry messages
     * 
     * @param array $messages
     * 
     * @return string
     */
    protected function createMessage(array $messages)
    {
        if (null === $this->messageFormat) {
            $this->messageFormat = $this->getDefaultMessageFormat();
        }
        
        $message = str_replace('%records%', implode('', $messages), $this->messageFormat);
        
        return $message;
    }
    
    /**
     * Prepare a record for writing
     * 
     * This method is just a shortcut for the common handling process (except writing)
     * 
     * @param array $record
     * 
     * @return array
     */
    protected function prepareRecord(array $record)
    {
        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        if (!$this->formatter) {
            $this->formatter = $this->getDefaultFormatter();
        }
        $record = $this->formatter->format($record);
        
        return $record;
    }
    
    /**
     * Get the default mail message format
     * 
     * @return string
     */
    protected function getDefaultMessageFormat()
    {
        return 'Application logs:\n %records%';
    }
    
    /**
     * Send a mail with the given message
     * 
     * @param string $message
     */
    abstract protected function send($message); 

}