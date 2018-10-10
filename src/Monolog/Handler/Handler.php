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

/**
 * Base Handler class providing basic close() support as well as handleBatch
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
abstract class Handler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
            // do nothing
        }
    }

    public function __sleep()
    {
        $this->close();

        return array_keys(get_object_vars($this));
    }
}
