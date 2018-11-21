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

/**
 * Adds a tags array into record
 *
 * @author Martijn Riemers
 */
class TagProcessor implements ProcessorInterface
{
    private $tags;

    public function __construct(array $tags = [])
    {
        $this->setTags($tags);
    }

    public function addTags(array $tags = []): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    public function setTags(array $tags = []): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function __invoke(array $record): array
    {
        $record['extra']['tags'] = $this->tags;

        return $record;
    }
}
