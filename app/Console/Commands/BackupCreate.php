<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

class BackupCreate extends Command
{
  protected $signature = 'backup:create {--type=manual : manual | scheduled | preclose}';
  protected $description = 'Create an encrypted, signed full database backup';

  public function handle(DatabaseBackup $backups): int
  {
    if (!in_array($this->option('type'), ['manual', 'scheduled', 'preclose'], true)) {
      $this->error('Type must be manual, scheduled or preclose.');
      return self::FAILURE;
    }
    try {
      $m = $backups->create($this->option('type'));
    } catch (\Throwable $e) {
      $this->error('Backup failed: ' . $e->getMessage());
      return self::FAILURE;
    }
    $this->info("Backup created: {$m['file']}");
    $this->line('  rows: ' . array_sum($m['row_counts']) . ' in ' . count($m['row_counts']) . ' tables; size ' . round($m['size'] / 1024) . ' KB');
    $this->line("  sha256: {$m['sha256']}");
    return self::SUCCESS;
  }
}
