<?php

namespace App\Console\Commands;

use App\Services\Closing\LegacySealer;
use Illuminate\Console\Command;

/**
 * Seals the backup currently loaded in the legacy scratch database.
 * Run by scripts/legacy-import.sh, which loads each backup file first.
 */
class LegacySealFile extends Command
{
  protected $signature = 'legacy:seal-file {file : Backup file name} {sha256 : SHA-256 of the backup file}';
  protected $description = 'Seal the backup loaded in sabong_legacy_tmp as a LEGACY closing package';

  public function handle(LegacySealer $sealer): int
  {
    try {
      $r = $sealer->sealLoadedBackup($this->argument('file'), $this->argument('sha256'));
    } catch (\Throwable $e) {
      $this->error($this->argument('file') . ': ' . $e->getMessage());
      return self::FAILURE;
    }
    if (!empty($r['skipped'])) {
      $this->line(sprintf('L%04d  %s  %s  (already sealed, skipped)', $r['sequence_no'], $r['event_date'], $r['file']));
      return self::SUCCESS;
    }
    $this->line(sprintf('L%04d  %s  %-26s fights %3d  net %14s  house %12s  seal %s%s',
      $r['sequence_no'], $r['event_date'], $r['file'], $r['fights'], number_format((float) $r['net_bets'], 2), number_format((float) $r['house_take'], 2),
      $r['seal_code'], $r['flags'] ? '  flags: ' . implode(',', $r['flags']) : ''));
    return self::SUCCESS;
  }
}
