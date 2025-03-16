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

use PHPUnit\Framework\Attributes\DataProvider;

class ClosureContextProcessorTest extends \Monolog\Test\MonologTestCase
{
    public function testReplace()
    {
        $context = ['obj' => new \stdClass()];
        $processor = new ClosureContextProcessor();

        $record = $processor($this->getRecord(context: [fn () => $context]));
        $this->assertSame($context, $record->context);
    }

    #[DataProvider('getContexts')]
    public function testSkip(array $context)
    {
        $processor = new ClosureContextProcessor();

        $record = $processor($this->getRecord(context: $context));
        $this->assertSame($context, $record->context);
    }

    public function testClosureReturnsNotArray()
    {
        $object = new \stdClass();
        $processor = new ClosureContextProcessor();

        $record = $processor($this->getRecord(context: [fn () => $object]));
        $this->assertSame([$object], $record->context);
    }

    public function testClosureThrows()
    {
        $exception = new \Exception('For test.');
        $expected = [
            'error_on_context_generation' => 'For test.',
            'exception' => $exception,
        ];
        $processor = new ClosureContextProcessor();

        $record = $processor($this->getRecord(context: [fn () => throw $exception]));
        $this->assertSame($expected, $record->context);
    }

    public static function getContexts(): iterable
    {
        yield [['foo']];
        yield [['foo' => 'bar']];
        yield [['foo', 'bar']];
        yield [['foo', fn () => 'bar']];
        yield [[fn () => 'foo', 'bar']];
        yield [['foo' => fn () => 'bar']];
    }
}
