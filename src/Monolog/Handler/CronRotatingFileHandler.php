<?php declare(strict_types=1);

namespace Monolog\Handler;

/*
 * This file is part of the Monolog package.
 *
 * (c) TheKing2 <theking2@king.ma>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Monolog\Level;
use Monolog\Utils;

/**
 * Stores logs to files that are rotated every day and a limited number of files are kept.
 *
 * This rotation is only intended to be used as a workaround. Using logrotate to
 * handle the rotation is strongly encouraged when you can use it.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class CronRotatingFileHandler extends StreamHandler implements HandlerInterface
{
  private $filename;
  private $stateFilename;

  /**
   * @param int      $maxFiles       The maximal amount of files to keep (0 means unlimited)
   * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
   * @param bool     $useLocking     Try to lock log file before doing any writes
   */
  public function __construct(
    string $filename,
    array $rotateSettings,
    int|string|Level $level = Level::Debug,
    bool $bubble = false,
    ?int $filePermission = null,
    bool $useLocking = false
  ) {
    $this->filename = Utils::canonicalizePath( $filename );
    $this->checkRotate( $rotateSettings );

    parent::__construct( $this->filename, $level, $bubble, $filePermission, $useLocking );
  }
  /**
   * checkRotate
   *
   * @return void
   */
  protected function checkRotate( array $rotateSettings ): void
  {
    $cronExpression = $rotateSettings['cronExpression'] ?? '* * */1 * *';
    $maxFiles       = $rotateSettings['maxFiles'] ?? 366;
    $minSize        = $rotateSettings['minSize'] ?? 0;
    $compress       = $rotateSettings['compress'] ?? false;


    $stateFilename = Utils::canonicalizePath( $this->filename . '.state' );
    $fileInfo      = new \SplFileInfo( $stateFilename );
    if( !$fileInfo->isFile() ) {
      touch( $stateFilename );
    }
    // create state file if not exist
    $rotation = ( new \Cesargb\Log\Rotation() )
      ->files( $maxFiles )
      ->minSize( $minSize );
    if( $compress ) {
      $rotation->compress();
    }
    $cron = new \Cron\CronExpression( $cronExpression );
    // check if log file is due based on state file modified time
    $dateTime     = new \DateTimeImmutable();                         // current datetime
    $filedateTime = $dateTime->setTimeStamp( $fileInfo->getMTime() ); // state-file datetime
    $nextRun      = $cron->getNextRunDate( $filedateTime );           // next-run datetime

    // if log file is due, rotate it
    if( $nextRun < $dateTime ) {
      // rotate log file
      touch( $fileInfo->getRealPath() );
      $rotation->rotate( $this->filename );

    }

  }
}

