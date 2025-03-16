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

class MercurialProcessorTest extends \Monolog\Test\MonologTestCase
{
    private string $oldCwd;
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oldCwd = getcwd();
        $this->testDir = sys_get_temp_dir().'/monolog-processor-mercurial-test';

        mkdir($this->testDir, recursive: true);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        chdir($this->oldCwd);

        if (!file_exists($this->testDir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir((string) $item) : unlink((string) $item);
        }

        rmdir($this->testDir);
    }

    /**
     * @covers Monolog\Processor\MercurialProcessor::__invoke
     */
    public function testProcessor()
    {
        if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
            exec("where hg 2>NUL", $output, $result);
        } else {
            exec("which hg 2>/dev/null >/dev/null", $output, $result);
        }
        if ($result != 0) {
            $this->markTestSkipped('hg is missing');

            return;
        }

        exec('hg init');
        exec('hg branch default');
        touch('test.txt');
        exec('hg add test.txt');
        exec('hg commit -u foo -m "initial commit"');

        $processor = new MercurialProcessor();
        $record = $processor($this->getRecord());

        $this->assertArrayHasKey('hg', $record->extra);
        $this->assertSame('default', $record->extra['hg']['branch']);
        $this->assertSame('0', $record->extra['hg']['revision']);
    }
}
