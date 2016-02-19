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
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;

/**
 * Helper trait for implementing FormattableInterface
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
trait FormattableHandlerTrait
{
    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter()
    {
        if (!$this->formatter) {
            $this->formatter = $this->getDefaultFormatter();
        }

        return $this->formatter;
    }

    /**
     * Gets the default formatter.
     *
     * @return FormatterInterface
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter();
    }
}
