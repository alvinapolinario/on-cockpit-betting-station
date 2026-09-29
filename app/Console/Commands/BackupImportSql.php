<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * TESTING ONLY: replace the database with a raw mysqldump/mariadb-dump file.
 * Disabled unless BACKUP_ALLOW_RAW_SQL_IMPORT=true, and never in production.
 */
class BackupImportSql extends Command
{
  protected $signature = 'backup:import-sql {file : File name inside storage/app/backups/import (.sql or .sql.gz)}
    {--check : Only parse and validate the file; change nothing}
    {--reason= : Why the import is needed (required, recorded permanently)}
    {--operator= : Your admin account id (recorded)}
    {--approver= : Second approver admin account id (recorded)}
    {--force : Skip the interactive confirmation}';
  protected $description = 'TESTING ONLY: validate or import a raw MySQL dump (replaces the whole database)';

  public function handle(DatabaseBackup $backups): int
  {
    $file = $this->argument('file');

    try {
      $c = $backups->checkSqlDump($file);
    } catch (\Throwable $e) {
      $this->error('Check FAILED: ' . $e->getMessage());
      return self::FAILURE;
    }
    $this->info("{$file}: {$c['statements']} statements, {$c['tables']} tables, {$c['views']} views, {$c['routines']} functions, {$c['triggers']} triggers, {$c['inserts']} insert statements.");
    $this->line("  sha256 {$c['sha256']}");
    if ($this->option('check')) return self::SUCCESS;

    $reason = trim((string) $this->option('reason'));
    if (strlen($reason) < 10) {
      $this->error('A --reason of at least 10 characters is required.');
      return self::FAILURE;
    }
    $this->warn('TESTING ONLY: this replaces the ENTIRE database with an unsigned dump. The current database is backed up first.');
    if (!$this->option('force') && $this->ask('Type RESTORE to continue') !== 'RESTORE') {
      $this->line('Cancelled.');
      return self::FAILURE;
    }

    try {
      $r = $backups->restoreSqlDump($file, [
        'account_id' => $this->option('operator') ? (int) $this->option('operator') : -1,
        'approved_by' => $this->option('approver') ? (int) $this->option('approver') : null,
        'reason' => $reason,
        'source' => 'cli',
      ]);
    } catch (\Throwable $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }
    $this->info("Imported {$r['statements']} statements ({$r['rows']} rows) in {$r['seconds']}s.");
    $this->line("Previous state saved as {$r['prerestore_backup']} (restore it to undo).");
    return self::SUCCESS;
  }
}
