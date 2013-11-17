<?php

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
 * Injects Git branch and Git commit SHA in all records
 *
 * @author Nick Otter
 */
class GitProcessor
{
    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {

        $branch = self::getBranch();
        $commit = self::getCommit();

        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'git' => array(
                    'branch' => $branch,
                    'commit' => $commit
                ),
            )
        );

        return $record;
    }

    static protected function getBranch() {
        $branches = explode("\n", `git branch`);

        foreach ($branches as $branch) {
            if ($branch[0] == "*") {
                return substr($branch, 2);
            }
        }
        return $branches;
    }

    static protected function getCommit() {
        $s = `git rev-parse HEAD`;
        return trim($s);
    }
}
