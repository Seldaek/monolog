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
 * TestHandler is used for testing purposes.
 *
 * It records all messages and gives you access to them for verification.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class TestHandler extends AbstractHandler
{
    protected $messages;
    protected $messagesByLevel;

    public function getMessages()
    {
        return $this->messages;
    }

    public function hasError($message)
    {
        return $this->hasMessage($message, Logger::ERROR);
    }

    public function hasWarning($message)
    {
        return $this->hasMessage($message, Logger::WARNING);
    }

    public function hasInfo($message)
    {
        return $this->hasMessage($message, Logger::INFO);
    }

    public function hasDebug($message)
    {
        return $this->hasMessage($message, Logger::DEBUG);
    }

    public function hasErrorMessages()
    {
        return isset($this->messagesByLevel[Logger::ERROR]);
    }

    public function hasWarningMessages()
    {
        return isset($this->messagesByLevel[Logger::WARNING]);
    }

    public function hasInfoMessages()
    {
        return isset($this->messagesByLevel[Logger::INFO]);
    }

    public function hasDebugMessages()
    {
        return isset($this->messagesByLevel[Logger::DEBUG]);
    }

    protected function hasMessage($message, $level = null)
    {
        if (null === $level) {
            $messages = $this->messages;
        } else {
            $messages = $this->messagesByLevel[$level];
        }
        foreach ($messages as $msg) {
            if ($msg['message'] === $message) {
                return true;
            }
        }
        return false;
    }

    public function write($message)
    {
        $this->messagesByLevel[$message['level']][] = $message;
        $this->messages[] = $message;
    }
}