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
 * Stores to PHP error_log() handler.
 *
 * @author Elan Ruusamäe <glen@delfi.ee>
 */
class ErrorLogHandler extends AbstractProcessingHandler
{

    const OPERATING_SYSTEM = 0;
    const MAIL = 1;
    const APPEND_FILE = 3;
    const SAPI = 4;

    protected $messageType;
    protected $destination;

    /**
     * @param integer $messageType Says where the error should go.
     * @param integer $level       The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble      Whether the messages that are handled can bubble up the stack or not
     * @param string  $destination Its meaning depends on the $messageType parameter as described.
     */
    public function __construct(
        $messageType = self::OPERATING_SYSTEM,
        $level = Logger::DEBUG,
        $bubble = true,
        $destination = null
    ) {
        parent::__construct($level, $bubble);

        if (false === in_array($messageType, self::getAvailableTypes())) {
            $message = sprintf('The given message type "%s" is not supported', print_r($messageType, true));
            throw new \InvalidArgumentException($message);
        }

        if ($destination === null
            && ($messageType === self::MAIL || $messageType === self::APPEND_FILE)) {
            $message = 'You must define a destination for the given type';
            throw new \UnexpectedValueException($message);
        }

        $this->messageType = $messageType;
        $this->destination = $destination;
    }

    /**
     * @return array With all available types
     */
    public static function getAvailableTypes()
    {
        return array(
            self::OPERATING_SYSTEM,
            self::MAIL,
            self::APPEND_FILE,
            self::SAPI,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        error_log((string) $record['formatted'], $this->messageType, $this->destination);
    }
}
