<?php declare(strict_types=1);

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
 * Stores to STDIN of any process, specified by a command.
 *
 * Usage example:
 * <pre>
 * $log = new Logger('myLogger');
 * $log->pushHandler(new ProcessHandler('/usr/bin/php /var/www/monolog/someScript.php'));
 * </pre>
 *
 * @author Kolja Zuelsdorf <koljaz@web.de>
 */
class ProcessHandler extends AbstractProcessingHandler
{
    /**
     * Holds the process to receive data on its STDIN.
     *
     * @var resource|bool|null
     */
    private $process;

    /**
     * @var string
     */
    private $command;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var array
     */
    private $pipes = [];

    /**
     * @var array
     */
    const DESCRIPTOR_SPEC = [
        0 => ['pipe', 'r'],  // STDIN is a pipe that the child will read from
        1 => ['pipe', 'w'],  // STDOUT is a pipe that the child will write to
        2 => ['pipe', 'w'],  // STDERR is a pipe to catch the any errors
    ];

    /**
     * @param  string                    $command Command for the process to start. Absolute paths are recommended,
     *                                            especially if you do not use the $cwd parameter.
     * @param  string|int                $level   The minimum logging level at which this handler will be triggered.
     * @param  bool                      $bubble  Whether the messages that are handled can bubble up the stack or not.
     * @param  string                    $cwd     "Current working directory" (CWD) for the process to be executed in.
     * @throws \InvalidArgumentException
     */
    public function __construct(string $command, $level = Logger::DEBUG, bool $bubble = true, string $cwd = null)
    {
        if ($command === '') {
            throw new \InvalidArgumentException('The command argument must be a non-empty string.');
        }
        if ($cwd === '') {
            throw new \InvalidArgumentException('The optional CWD argument must be a non-empty string or null.');
        }

        parent::__construct($level, $bubble);

        $this->command = $command;
        $this->cwd = $cwd;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array                     $record
     * @throws \UnexpectedValueException
     * @return void
     */
    protected function write(array $record)
    {
        $this->ensureProcessIsStarted();

        $this->writeProcessInput($record['formatted']);

        $errors = $this->readProcessErrors();
        if (empty($errors) === false) {
            throw new \UnexpectedValueException(sprintf('Errors while writing to process: %s', $errors));
        }
    }

    /**
     * Makes sure that the process is actually started, and if not, starts it,
     * assigns the stream pipes, and handles startup errors, if any.
     *
     * @return void
     */
    private function ensureProcessIsStarted()
    {
        if (is_resource($this->process) === false) {
            $this->startProcess();

            $this->handleStartupErrors();
        }
    }

    /**
     * Starts the actual process and sets all streams to non-blocking.
     *
     * @return void
     */
    private function startProcess()
    {
        $this->process = proc_open($this->command, self::DESCRIPTOR_SPEC, $this->pipes, $this->cwd);

        foreach ($this->pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
    }

    /**
     * Selects the STDERR stream, handles upcoming startup errors, and throws an exception, if any.
     *
     * @throws \UnexpectedValueException
     * @return void
     */
    private function handleStartupErrors()
    {
        $selected = $this->selectErrorStream();
        if (false === $selected) {
            throw new \UnexpectedValueException('Something went wrong while selecting a stream.');
        }

        $errors = $this->readProcessErrors();

        if (is_resource($this->process) === false || empty($errors) === false) {
            throw new \UnexpectedValueException(
                sprintf('The process "%s" could not be opened: ' . $errors, $this->command)
            );
        }
    }

    /**
     * Selects the STDERR stream.
     *
     * @return int|bool
     */
    protected function selectErrorStream()
    {
        $empty = [];
        $errorPipes = [$this->pipes[2]];

        return stream_select($errorPipes, $empty, $empty, 1);
    }

    /**
     * Reads the errors of the process, if there are any.
     *
     * @codeCoverageIgnore
     * @return string Empty string if there are no errors.
     */
    protected function readProcessErrors()
    {
        return stream_get_contents($this->pipes[2]);
    }

    /**
     * Writes to the input stream of the opened process.
     *
     * @codeCoverageIgnore
     * @param $string
     * @return void
     */
    protected function writeProcessInput($string)
    {
        fwrite($this->pipes[0], (string) $string);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (is_resource($this->process)) {
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->process);
            $this->process = null;
        }
    }
}
