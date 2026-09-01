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

use Closure;
use Monolog\Level;
use Monolog\Logger;
use Monolog\ResettableInterface;
use Monolog\Formatter\FormatterInterface;
use Psr\Log\LogLevel;
use Monolog\LogRecord;

/**
 * Watches log records go by and expects to be fed at least $hunger of them before
 * the process ends. If it stays hungry until close() (and was not manually fed()),
 * the monster gets angry and emits a complaint record at $angerLevel to the nested
 * handler. Useful as a dead-man's switch for cron jobs / workers that should produce
 * a known amount of log activity before they finish, or to make sure that every code
 * path / request in the application at least logs some amount of records.
 *
 * Records carrying context are "cookies with chocolate chips" — when $wantsContextChips
 * is true, only records with non-empty context count toward feeding the monster.
 *
 * Thanks to Jonathan Wage for the idea https://x.com/seldaek/status/1491110384023277569
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class LogMonsterHandler extends Handler implements ProcessableHandlerInterface, ResettableInterface, FormattableHandlerInterface
{
    use ProcessableHandlerTrait;

    /**
     * Handler or factory Closure($record, $this)
     *
     * @phpstan-var (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface
     */
    protected Closure|HandlerInterface $handler;

    protected int $hunger;

    protected Level $angerLevel;

    protected string $channel;

    protected bool $wantsContextChips;

    /** Number of records the monster has eaten so far */
    protected int $eaten = 0;

    /** Whether the monster has been manually fed and should stay quiet on close */
    protected bool $fed = false;

    /**
     * @phpstan-param (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface $handler
     *
     * @param Closure|HandlerInterface     $handler           Handler or factory Closure($record|null, $logMonsterHandler).
     * @param int                          $hunger            Number of log records the monster must eat before close() to stay satisfied.
     * @param int|string|Level|LogLevel::* $angerLevel        Level at which the monster logs its complaint when it goes hungry.
     * @param string                       $channel           Channel name stamped on the synthetic complaint record, note the record only gets sent to the given $handler, and will for example not go to the handlers configured for $channel in Symfony.
     * @param bool                         $wantsContextChips If true, only records carrying context count toward feeding the monster.
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $angerLevel
     */
    public function __construct(Closure|HandlerInterface $handler, int $hunger, int|string|Level $angerLevel = Level::Error, string $channel = 'log-monster', bool $wantsContextChips = false)
    {
        $this->handler = $handler;
        $this->hunger = $hunger;
        $this->channel = $channel;
        $this->wantsContextChips = $wantsContextChips;
        $this->angerLevel = Logger::toMonologLevel($angerLevel);
    }

    /**
     * @inheritDoc
     */
    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    /**
     * Manually feed the log monster so it does not complain on close (until reset() is called)
     */
    public function feed(): void
    {
        $this->fed = true;
    }

    /**
     * @inheritDoc
     */
    public function handle(LogRecord $record): bool
    {
        if (!$this->wantsContextChips || \count($record->context) > 0) {
            $this->eaten++;
        }

        // the monster only watches records go by, it never consumes them
        return false;
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (!$this->fed && $this->eaten < $this->hunger) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable('now'),
                channel: $this->channel,
                level: $this->angerLevel,
                message: 'Om nom nom... the log monster is hangry: it only ate '.$this->eaten.' of '.$this->hunger.' expected log records',
                context: ['eaten' => $this->eaten, 'hunger' => $this->hunger],
            );

            if (\count($this->processors) > 0) {
                $record = $this->processRecord($record);
            }

            $this->getHandler($record)->handle($record);
        }

        $this->getHandler()->close();
    }

    public function reset(): void
    {
        $this->fed = false;
        $this->eaten = 0;

        $this->resetProcessors();

        if ($this->getHandler() instanceof ResettableInterface) {
            $this->getHandler()->reset();
        }
    }

    /**
     * Return the nested handler
     *
     * If the handler was provided as a factory, this will trigger the handler's instantiation.
     */
    public function getHandler(?LogRecord $record = null): HandlerInterface
    {
        if (!$this->handler instanceof HandlerInterface) {
            $handler = ($this->handler)($record, $this);
            if (!$handler instanceof HandlerInterface) {
                throw new \RuntimeException("The factory Closure should return a HandlerInterface");
            }
            $this->handler = $handler;
        }

        return $this->handler;
    }

    /**
     * @inheritDoc
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        $handler = $this->getHandler();
        if ($handler instanceof FormattableHandlerInterface) {
            $handler->setFormatter($formatter);

            return $this;
        }

        throw new \UnexpectedValueException('The nested handler of type '.\get_class($handler).' does not support formatters.');
    }

    /**
     * @inheritDoc
     */
    public function getFormatter(): FormatterInterface
    {
        $handler = $this->getHandler();
        if ($handler instanceof FormattableHandlerInterface) {
            return $handler->getFormatter();
        }

        throw new \UnexpectedValueException('The nested handler of type '.\get_class($handler).' does not support formatters.');
    }
}
