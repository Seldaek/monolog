<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Daniel Costa <danielcosta@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use PDO;
use PDOStatement;

/**
 * Class PDOHandler
 *
 * @package Monolog\Handler
 * @author Daniel Costa <danielcosta@gmail.com>
 */
class PDOHandler extends AbstractProcessingHandler
{
    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var PDOStatement
     */
    private $statement;

    /**
     * @var string
     */
    private $table;

    /**
     * @param PDO    $pdo
     * @param string $table
     * @param string $level
     * @param bool   $bubble
     */
    public function __construct(PDO $pdo, $table = 'monolog', $level = Logger::DEBUG, $bubble = true)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $this->statement->execute(array(
            'channel' => $record['channel'],
            'level' => $record['level'],
            'message' => $record['formatted'],
            'time' => $record['datetime']->format('U'),
        ));
    }

    private function initialize()
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->table
            . '(channel VARCHAR(255), level INTEGER, message LONGTEXT, time INTEGER UNSIGNED)'
        );
        $this->statement = $this->pdo->prepare(
            'INSERT INTO '.$this->table.' (channel, level, message, time) VALUES (:channel, :level, :message, :time)'
        );

        $this->initialized = true;
    }
}