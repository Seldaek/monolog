<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Processor;

use Monolog\ResettableInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Adds a unique identifier into records
 *
 * @author George Mponos <gmponos@gmail.com>
 */
class RamseyUuidProcessor implements ProcessorInterface, ResettableInterface
{
    private $uuid;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
    }

    public function __invoke(array $record): array
    {
        $record['extra']['uuid'] = $this->uuid->toString();

        return $record;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function reset()
    {
        $this->uuid = Uuid::uuid4();
    }
}
