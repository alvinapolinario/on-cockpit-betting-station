<?php

namespace App\Console\Commands;

use App\Services\Closing\KeyStore;
use Illuminate\Console\Command;

class BackupKeygen extends Command
{
  protected $signature = 'backup:keygen {--force : Replace an existing key (existing backups become unreadable)}';
  protected $description = 'Generate the local encryption key for database backups';

  public function handle(KeyStore $keys): int
  {
    try {
      $fp = $keys->generateBackupKey((bool) $this->option('force'));
    } catch (\RuntimeException $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }
    $this->info('Backup key created at ' . $keys->path('backup.key') . " (fingerprint {$fp}).");
    $this->warn('Keep a sealed offline copy of this key. Without it, no backup can be restored.');
    return self::SUCCESS;
  }
}
