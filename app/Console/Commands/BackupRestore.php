<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Console restore: the preferred path in a real disaster, since it works even
 * when the web app is unusable. Requires physical/SSH access to the server.
 */
class BackupRestore extends Command
{
  protected $signature = 'backup:restore {file : Backup file name (.bak.enc)}
    {--reason= : Why the restore is needed (required, recorded permanently)}
    {--operator= : Your admin account id (recorded)}
    {--approver= : Second approver admin account id (recorded)}
    {--allow-unsealing : DANGEROUS: allow restoring a backup older than a sealed closing}
    {--force : Skip the interactive confirmation}';
  protected $description = 'Restore the database from a backup (takes a pre-restore backup first; rolls back on failure)';

  public function handle(DatabaseBackup $backups): int
  {
    $file = $this->argument('file');
    $reason = trim((string) $this->option('reason'));
    if (strlen($reason) < 10) {
      $this->error('A --reason of at least 10 characters is required.');
      return self::FAILURE;
    }

    try {
      $m = $backups->loadManifest($file);
    } catch (\Throwable $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }

    $this->warn("Restoring {$file}");
    $this->line("  taken {$m['created_at']} ({$m['type']}), " . array_sum($m['row_counts']) . ' rows');
    if ($active = DB::table('events')->where('event_status', 'Active')->first()) {
      $this->warn("  Event #{$active->event_id} {$active->event_name} is ACTIVE. Betting and payouts must be stopped.");
    }
    if ($this->option('allow-unsealing')) {
      $this->error('  --allow-unsealing is set: sealed closings newer than this backup will be removed from the database.');
    }

    if (!$this->option('force') && $this->ask('Everything after the backup time will be replaced. Type RESTORE to continue') !== 'RESTORE') {
      $this->line('Cancelled.');
      return self::FAILURE;
    }

    try {
      $res = $backups->restore($file, [
        'account_id' => $this->option('operator') ? (int) $this->option('operator') : -1,
        'approved_by' => $this->option('approver') ? (int) $this->option('approver') : null,
        'reason' => $reason,
        'allow_unsealing' => (bool) $this->option('allow-unsealing'),
        'source' => 'cli',
      ]);
    } catch (\Throwable $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }

    $this->info("Restored {$res['rows']} rows in {$res['seconds']}s.");
    $this->line("Previous state saved as {$res['prerestore_backup']} (restore it to undo).");
    return self::SUCCESS;
  }
}
