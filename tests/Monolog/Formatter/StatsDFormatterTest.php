<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

/**
 * @covers Monolog\Formatter\StatsDFormatter
 */
class StatsDFormatterTest extends \PHPUnit_Framework_TestCase
{ 
    public function testBatchFormat()
    {
        $formatter = new StatsDFormatter(null, 2);
        $message = $formatter->formatBatch(array(
            array(
                'level_name' => 'CRITICAL',
                'channel' => 'test',
                'message' => 'bar',
                'context' => array(),
                'datetime' => new \DateTime,
                'extra' => array(),
            ),
            array(
                'level_name' => 'WARNING',
                'channel' => 'log',
                'message' => 'foo',
                'context' => array(),
                'datetime' => new \DateTime,
                'extra' => array(),
            ),
        ));

        $this->assertEquals(array('test.CRITICAL.bar', 'log.WARNING.foo'), $message);
    }
 
    public function testDefFormatWithString()
    {
        $formatter = new StatsDFormatter(StatsDFormatter::SIMPLE_FORMAT);
        $message = $formatter->format(array(
            'level_name' => 'WARNING',
            'channel' => 'log',
            'context' => array(),
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array(),
        ));
        $this->assertEquals(array('log.WARNING.foo'), $message);
    }
 
    public function testDefFormatWithArrayContext()
    {
        $formatter = new StatsDFormatter();
        $message = $formatter->format(array(
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array(),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ));

        $assert = array('meh.ERROR.foo',
            'meh.ERROR.foo.context.foo.bar',
            'meh.ERROR.foo.context.baz.qux');

        $this->assertEquals($assert, $message);
    }
  
    public function testDefFormatWithArrayContextAndExtra()
    {
        $formatter = new StatsDFormatter();
        $message = $formatter->format(array(
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => new \DateTime,
            'extra' => array('extra'=>'woow'),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ));
        
        $assert = array('meh.ERROR.foo',
            'meh.ERROR.foo.context.foo.bar',
            'meh.ERROR.foo.context.baz.qux',
            'meh.ERROR.foo.extra.extra.woow');

        $this->assertEquals($assert, $message);

    }
 
    public function testDefLongFormat()
    {
        $formatter = new StatsDFormatter();
        $message = $formatter->format(array(
            'level_name' => 'DEBUG',
            'channel' => 'doctrine',
            'message' => 'INSERT INTO viaggio_calendar (enable, viaggio_id, calendar_id) VALUES (?, ?, ?)',
            'datetime' => new \DateTime,
            'extra' => array(),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ));
        $this->assertEquals(array("doctrine.DEBUG.INSERT-INTO",
            "doctrine.DEBUG.INSERT-INTO.context.foo.bar",
            "doctrine.DEBUG.INSERT-INTO.context.baz.qux"), $message);
    } 

    public function testDefLongFormatWith3WordsNoContextAndNoExtra()
    {
        $formatter = new StatsDFormatter(null, false, false, 3);
        $message = $formatter->format(array(
            'level_name' => 'DEBUG',
            'channel' => 'doctrine',
            'message' => 'INSERT INTO viaggio_calendar (enable, viaggio_id, calendar_id) VALUES (?, ?, ?)',
            'datetime' => new \DateTime,
            'extra' => array(),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ));
        $this->assertEquals(array("doctrine.DEBUG.INSERT-INTO-viaggio-calendar"), $message);
    }
    public function testDefRouteException()
    {
        $formatter = new StatsDFormatter();
        $message = $formatter->format(array(
            'level_name' => 'DEBUG',
            'channel' => 'doctrine',
            'message' => 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException: No route found for "GET /ddd" (uncaught exception) at /xxxx/classes.php line 5062',
            'datetime' => new \DateTime,
            'extra' => array(),
        ));
        $this->assertEquals(array('doctrine.DEBUG.Symfony-Component-HttpKernel-Exception-NotFoundHttpException--No'), $message);
    } 

    public function testDefKernelException()
    {
        $formatter = new StatsDFormatter();
        $message = $formatter->format(array(
            'level_name' => 'DEBUG',
            'channel' => 'doctrine',
            'message' => 'Notified event "kernel.exception" to listener "Symfony\Component\HttpKernel\EventListener\ProfilerListener::onKernelException"',
            'datetime' => new \DateTime,
            'extra' => array(),
            'context' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            )
        ));
 
        $assert = array('doctrine.DEBUG.Notified-event',
            'doctrine.DEBUG.Notified-event.context.foo.bar',
            'doctrine.DEBUG.Notified-event.context.baz.qux');

        $this->assertEquals($assert, $message);

       
    }
}
 
