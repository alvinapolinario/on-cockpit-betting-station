<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

class BackupList extends Command
{
  protected $signature = 'backup:list';
  protected $description = 'List database backups and their integrity status';

  public function handle(DatabaseBackup $backups): int
  {
    $rows = array_map(fn ($b) => [
      $b['created_at'] ?? '?', $b['type'] ?? '?', $b['file'] ?? '?',
      isset($b['row_counts']) ? array_sum($b['row_counts']) : '?',
      count((array) ($b['seals'] ?? [])),
      $b['_valid'] ? 'OK' : 'INVALID: ' . $b['_error'],
    ], $backups->list());
    $this->table(['Created', 'Type', 'File', 'Rows', 'Seals', 'Integrity'], $rows);
    return self::SUCCESS;
  }
}
