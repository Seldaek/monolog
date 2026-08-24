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

use Monolog\Test\TestCase;
use Monolog\Logger;

/**
 * @covers Monolog\Handler\BrowserConsoleHandlerTest
 */
class BrowserConsoleHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        BrowserConsoleHandler::resetStatic();
    }

    protected function generateScript()
    {
        $reflMethod = new \ReflectionMethod('Monolog\Handler\BrowserConsoleHandler', 'generateScript');
        $reflMethod->setAccessible(true);

        return $reflMethod->invoke(null);
    }

    public function testStyling()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, 'foo[[bar]]{color: red}'));

        $expected = <<<EOF
(function (c) {if (c && c.groupCollapsed) {
c.debug("%cfoo%cbar%c", "font-weight: normal", "color: red", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testStylingMultiple()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, 'foo[[bar]]{color: red}[[baz]]{color: blue}'));

        $expected = <<<EOF
(function (c) {if (c && c.groupCollapsed) {
c.debug("%cfoo%cbar%c%cbaz%c", "font-weight: normal", "color: red", "font-weight: normal", "color: blue", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testEscaping()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, "[foo] [[\"bar\n[baz]\"]]{color: red}"));

        $expected = <<<EOF
(function (c) {if (c && c.groupCollapsed) {
c.debug("%c[foo] %c\"bar\\n[baz]\"%c", "font-weight: normal", "color: red", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testAutolabel()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, '[[foo]]{macro: autolabel}'));
        $handler->handle($this->getRecord(Logger::DEBUG, '[[bar]]{macro: autolabel}'));
        $handler->handle($this->getRecord(Logger::DEBUG, '[[foo]]{macro: autolabel}'));

        $expected = <<<EOF
(function (c) {if (c && c.groupCollapsed) {
c.debug("%c%cfoo%c", "font-weight: normal", "background-color: blue; color: white; border-radius: 3px; padding: 0 2px 0 2px", "font-weight: normal");
c.debug("%c%cbar%c", "font-weight: normal", "background-color: green; color: white; border-radius: 3px; padding: 0 2px 0 2px", "font-weight: normal");
c.debug("%c%cfoo%c", "font-weight: normal", "background-color: blue; color: white; border-radius: 3px; padding: 0 2px 0 2px", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testContext()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, 'test', ['foo' => 'bar', 0 => 'oop']));

        $expected = <<<EOF
(function (c) {if (c && c.groupCollapsed) {
c.groupCollapsed("%ctest", "font-weight: normal");
c.log("%c%s", "font-weight: bold", "Context");
c.log("%s: %o", "foo", "bar");
c.log("%s: %o", "0", "oop");
c.groupEnd();
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testConcurrentHandlers()
    {
        $handler1 = new BrowserConsoleHandler();
        $handler1->setFormatter($this->getIdentityFormatter());

        $handler2 = new BrowserConsoleHandler();
        $handler2->setFormatter($this->getIdentityFormatter());

        $handler1->handle($this->getRecord(Logger::DEBUG, 'test1'));
        $handler2->handle($this->getRecord(Logger::DEBUG, 'test2'));
        $handler1->handle($this->getRecord(Logger::DEBUG, 'test3'));
        $handler2->handle($this->getRecord(Logger::DEBUG, 'test4'));

        $expected = <<<EOF
(function (c) {if (c && c.groupCollapsed) {
c.debug("%ctest1", "font-weight: normal");
c.debug("%ctest2", "font-weight: normal");
c.debug("%ctest3", "font-weight: normal");
c.debug("%ctest4", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testEscapesScriptEndTagInMessage()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, 'Search for </script><script>alert(document.domain)</script> completed'));

        $expected = <<<'EOF'
(function (c) {if (c && c.groupCollapsed) {
c.debug("%cSearch for \u003C/script\u003E\u003Cscript\u003Ealert(document.domain)\u003C/script\u003E completed", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testEscapesHtmlCommentScriptOpenInMessage()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        // this one needs no slash at all, it puts the HTML tokenizer in script data double escaped
        // state where the handler's own </script> no longer closes the element
        $handler->handle($this->getRecord(Logger::DEBUG, '<!--<script>'));

        $expected = <<<'EOF'
(function (c) {if (c && c.groupCollapsed) {
c.debug("%c\u003C!--\u003Cscript\u003E", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testEscapesCarriageReturn()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, "foo\r\nbar"));

        $expected = <<<'EOF'
(function (c) {if (c && c.groupCollapsed) {
c.debug("%cfoo\r\nbar", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testEscapesLineSeparators()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        // U+2028/U+2029 are JS line terminators, a raw one ends the string literal
        $handler->handle($this->getRecord(Logger::DEBUG, "foo\u{2028}bar\u{2029}baz"));

        $expected = <<<'EOF'
(function (c) {if (c && c.groupCollapsed) {
c.debug("%cfoo\u2028bar\u2029baz", "font-weight: normal");
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testEscapesScriptEndTagInContext()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $handler->handle($this->getRecord(Logger::DEBUG, 'test', ['q' => '</script><!--<script>']));

        $expected = <<<'EOF'
(function (c) {if (c && c.groupCollapsed) {
c.groupCollapsed("%ctest", "font-weight: normal");
c.log("%c%s", "font-weight: bold", "Context");
c.log("%s: %o", "q", "\u003C/script\u003E\u003C!--\u003Cscript\u003E");
c.groupEnd();
}})(console);
EOF;

        $this->assertEquals($expected, $this->generateScript());
    }

    public function testGeneratedScriptContainsNoAngleBracket()
    {
        $handler = new BrowserConsoleHandler();
        $handler->setFormatter($this->getIdentityFormatter());

        $payload = '</script><!--<script>';
        $handler->handle($this->getRecord(Logger::DEBUG, $payload.' [['.$payload.']]{color: red}', ['q' => $payload]));

        // no literal < can reach the output, so the HTML tokenizer never leaves script data state
        // and only the handler's own </script> can close the element
        $this->assertStringNotContainsString('<', $this->generateScript());
    }
}
