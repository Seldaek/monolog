<?php declare(strict_types=1);

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
 * @covers Monolog\Formatter\HtmlFormatter
 */
class HtmlFormatterTest extends \Monolog\Test\MonologTestCase
{
    public function testKeepsTextContainingInvalidUtf8()
    {
        $formatter = new HtmlFormatter('Y-m-d');

        // "café" in ISO-8859-1, which htmlspecialchars() turns into an empty string unless told otherwise
        $output = $formatter->format($this->getRecord(
            message: "import of caf\xE9 <menu> failed",
            context: ["caf\xE9" => "caf\xE9.csv"],
        ));

        $this->assertStringContainsString("<pre>import of caf\u{FFFD} &lt;menu&gt; failed</pre>", $output);
        $this->assertStringContainsString("caf\u{FFFD}:</th>", $output);
        $this->assertStringContainsString("<pre>caf\u{FFFD}.csv</pre>", $output);
    }
}
