<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Util;

use Symfony\Component\Process\Process;

class LocalSocket
{
    const TCP = 'tcp';
    const UDP = 'udp';

    private static $sockets = [];
    private static $shutdownHandler = false;

    public static function initSocket(int $port = 51984, string $proto = LocalSocket::TCP)
    {
        if (!isset(self::$sockets[$proto][$port])) {
            $file = self::initFile($port, $proto);

            $process = new Process(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file));
            $process->start(function ($type, $out) use ($proto, $port) {
                if ($type === 'err') {
                    if (substr($out, 0, 4) === 'INIT') {
                        if ($proto === LocalSocket::UDP) {
                            self::$sockets[$proto][$port]['comms'] = null;
                        } else {
                            $sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname($proto));
                            socket_connect($sock, '127.0.0.1', $port);
                            socket_write($sock, "MONITOR\n");
                            self::$sockets[$proto][$port]['comms'] = $sock;
                        }
                    }
                }
            });

            self::$sockets[$proto][$port] = [
                'file' => $file,
                'process' => $process,
                'busy' => false,
            ];

            // make sure the socket is listening
            while (true) {
                if ($process->getErrorOutput() === 'INIT') {
                    break;
                }
                usleep(100);
            }

            if (!self::$shutdownHandler) {
                register_shutdown_function(function () {
                    LocalSocket::shutdownSockets();
                });
                self::$shutdownHandler = true;
            }
        }

        $sock = self::$sockets[$proto][$port];
        if (!$sock['process']->isRunning()) {
            throw new \RuntimeException(
                'LocalSocket '.$proto.'://127.0.0.1:'.$port.' appears to have died unexpectedly: ' . "\n\n" .
                $sock['process']->getOutput()
            );
        }

        self::clearSocket($port, $proto);

        return new class($sock['process'], $sock['comms']) {
            public function __construct(Process $proc, $comms)
            {
                $this->process = $proc;
                $this->comms = $comms;
            }

            public function getOutput()
            {
                // read out until getting a !DONE! ack and then tell the socket to terminate the connection
                if ($this->comms) {
                    $out = '';
                    socket_write($this->comms, "DONE?\n");
                    while ($data = socket_read($this->comms, 2048)) {
                        $out .= $data;
                        if (substr($out, -6) === '!DONE!') {
                            $out = substr($out, 0, -6);
                            break;
                        }
                    }
                    $out = preg_replace('{.*!BEGIN!}', '', $out);

                    socket_write($this->comms, "TERMINATE\n");

                    return $out;
                }

                // wait 3 seconds max for output for UDP
                $retries = 3000;
                while (!$this->process->getOutput() && $retries-- && $this->process->getStatus()) {
                    usleep(100);
                }

                return $this->process->getOutput();
            }
        };
    }

    private static function clearSocket(int $port = 51984, string $proto = LocalSocket::TCP)
    {
        if (isset(self::$sockets[$proto][$port])) {
            self::$sockets[$proto][$port]['process']->clearOutput();
        }
    }

    public static function shutdownSocket(int $port = 51984, string $proto = LocalSocket::TCP)
    {
        if (!isset(self::$sockets[$proto][$port])) {
            return;
        }

        if (is_resource(self::$sockets[$proto][$port]['comms'])) {
            socket_write(self::$sockets[$proto][$port]['comms'], "EXIT\n");
            socket_close(self::$sockets[$proto][$port]['comms']);
        }
        $sock = self::$sockets[$proto][$port];
        $sock['process']->stop();
        @unlink($sock['file']);
        unset(self::$sockets[$proto][$port]);
    }

    public static function shutdownSockets()
    {
        foreach (self::$sockets as $proto => $ports) {
            foreach ($ports as $port => $sock) {
                self::shutdownSocket($port, $proto);
            }
        }
    }

    private static function initFile(int $port, string $proto): string
    {
        $tmpFile = sys_get_temp_dir().'/monolog-test-'.$proto.'-socket-'.$port.'.php';

        if ($proto === self::UDP) {
            file_put_contents($tmpFile, <<<SCRIPT
<?php

\$sock = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
if (!socket_bind(\$sock, '127.0.0.1', $port)) {
    fwrite(STDERR, 'COULD NOT BIND $port');
}
fwrite(STDERR, 'INIT');
while (true) {
    socket_recvfrom(\$sock, \$read, 100*1024, 0, \$ip, \$port);
    fwrite(STDOUT, \$read);
}
SCRIPT
            );
        } else {
            file_put_contents($tmpFile, <<<SCRIPT
<?php

\$sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
if (!socket_bind(\$sock, '127.0.0.1', $port)) {
    fwrite(STDERR, 'COULD NOT BIND $port');
}
if (!socket_listen(\$sock)) {
    fwrite(STDERR, 'COULD NOT LISTEN $port');
}
fwrite(STDERR, 'INIT');

\$monitor = socket_accept(\$sock);
\$read = socket_read(\$monitor, 1024, PHP_NORMAL_READ);
if (substr(\$read, 0, 7) !== 'MONITOR') {
    fwrite(STDERR, "Unexpected input: \$read");
} else {
    fwrite(STDERR, "MONITORED");
}

while (true) {
    \$res = socket_accept(\$sock);
    socket_set_option(\$res, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 0));
    socket_write(\$monitor, '!BEGIN!');

    while (true) {
        \$read = [\$res, \$monitor, \$sock];
        \$write = [];
        \$except = [];
        \$timeout = 0;

        if (socket_select(\$read, \$write, \$except, \$timeout) < 1) {
            continue;
        }

        foreach (\$read as \$readsock) {
            if (\$readsock === \$res) {
                \$bytes = socket_read(\$res, 1024);
                //if (\$bytes === '' && in_array(\$sock, \$read)) {
                //    // client closed
                //    socket_write(\$monitor, 'CLIENTCLOSED');
                //    break 2;
                //}
                socket_write(\$monitor, \$bytes);
            } else {
                \$bytes = socket_read(\$monitor, 1024, PHP_NORMAL_READ);
                if (substr(trim(\$bytes), 0, 9) === 'TERMINATE') {
                    break 2;
                } elseif (substr(trim(\$bytes), 0, 5) === 'DONE?') {
                    socket_write(\$monitor, '!DONE!');
                } elseif (substr(trim(\$bytes), 0, 5) === 'EXIT') {
                    socket_close(\$res);
                    socket_close(\$monitor);
                    die;
                }
            }
        }
    }

    socket_close(\$res);
}
SCRIPT
            );
        }

        return $tmpFile;
    }
}
