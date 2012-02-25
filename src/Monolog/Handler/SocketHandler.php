<?php
/**
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 */

namespace Monolog\Handler;

use Monolog\Handler\SocketHandler\Socket;
use Monolog\Handler\SocketHandler\PersistentSocket;
use Monolog\Logger;

/**
 * Stores to any socket - uses fsockopen() or pfsockopen().
 * 
 * @see Monolog\Handler\SocketHandler\Socket
 * @see Monolog\Handler\SocketHandler\PersistentSocket
 * @see http://php.net/manual/en/function.fsockopen.php
 */
class SocketHandler extends AbstractProcessingHandler
{
    /**
     * @var Socket
     */
    private $socket;
 
    /**
     * @param string $connectionString
     * @param integer $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($connectionString, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->socket = new Socket($connectionString);
    }

    /**
     * Inject socket - allows you to configure timeouts.
     * 
     * @param Socket $socket 
     */
    public function setSocket(Socket $socket)
    {
        $this->socket = $socket;
    }
    
    /**
     * We will not close a PersistentSocket instance so it can be reused in other requests.
     */
    public function close()
    {
        if ($this->socket instanceof PersistentSocket) {
            return;
        }
        $this->socket->close();
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $this->socket->write((string) $record['formatted']);
    }
}
