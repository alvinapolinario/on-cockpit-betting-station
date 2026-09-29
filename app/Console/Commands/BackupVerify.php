<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

class BackupVerify extends Command
{
  protected $signature = 'backup:verify {file : Backup file name (.bak.enc)}';
  protected $description = 'Fully verify a backup (signature, hash, decryption, row counts) without changing anything';

  public function handle(DatabaseBackup $backups): int
  {
    try {
      $r = $backups->verify($this->argument('file'));
    } catch (\Throwable $e) {
      $this->error('FAILED: ' . $e->getMessage());
      return self::FAILURE;
    }
    $this->info("OK: {$r['rows']} rows in {$r['tables']} tables; signature, hash and encryption verified.");
    return self::SUCCESS;
  }
}
