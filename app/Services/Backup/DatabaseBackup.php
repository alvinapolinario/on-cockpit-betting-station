<?php

namespace App\Services\Backup;

use App\Models\EventClosing;
use App\Services\Closing\Canonical;
use App\Services\Closing\EventSealer;
use App\Services\Closing\KeyStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Full database backup and restore, without external tools (no mysqldump).
 *
 * A backup is two files:
 *   <name>.bak.enc        encrypted, compressed stream of schema + rows (JSON lines)
 *   <name>.manifest.json  metadata + row counts + SHA-256 of the .bak.enc,
 *                         signed with this server's Ed25519 key
 *
 * Restore only accepts backups whose manifest signature, file hash, stream
 * authentication and row counts all check out. Rows are inserted with
 * prepared statements; nothing in a backup is ever executed as free SQL
 * except the schema statements this server itself wrote and signed.
 */
class DatabaseBackup
{
  public function __construct(private KeyStore $keys) {}

  // ================================================================ create

  public function create(string $type = 'manual', ?int $accountId = null): array
  {
    if (!in_array($type, ['manual', 'scheduled', 'prerestore', 'preclose'], true)) {
      throw new \InvalidArgumentException("Unknown backup type {$type}");
    }
    @set_time_limit(0);
    $key = $this->keys->backupKey();
    $this->keys->signingSecret(); // fail early if no signing key

    return $this->withLock(function () use ($type, $accountId, $key) {
      $pdo = $this->pdo();
      $db = config('database.connections.mysql.database');
      $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
      $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

      $active = $pdo->query("SELECT event_id, event_name, event_date FROM events WHERE event_status = 'Active' ORDER BY event_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
      $name = sprintf('%s_E%04d_%s_%s_%s',
        $active['event_date'] ?? date('Y-m-d'), $active['event_id'] ?? 0, $type,
        date('Ymd\THis'), preg_replace('/[^A-Za-z0-9-]/', '', config('sealing.server_id')));
      $path = $this->dir() . "/{$name}.bak.enc";

      $stream = EncryptedStream::create($path, $key);
      $counts = [];
      $objects = ['routines' => 0, 'tables' => 0, 'views' => 0, 'triggers' => 0];
      try {
        $stream->writeLine(Canonical::encode(['t' => 'header', 'format' => config('backup.format'), 'database' => $db, 'created_at' => date('Y-m-d H:i:s')]));

        // Stored functions / procedures
        $routines = $pdo->prepare('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? ORDER BY ROUTINE_NAME');
        $routines->execute([$db]);
        foreach ($routines->fetchAll(PDO::FETCH_ASSOC) as $r) {
          $kind = strtoupper($r['ROUTINE_TYPE']) === 'PROCEDURE' ? 'PROCEDURE' : 'FUNCTION';
          $row = $pdo->query("SHOW CREATE {$kind} `{$r['ROUTINE_NAME']}`")->fetch(PDO::FETCH_ASSOC);
          $sql = $row['Create ' . ucfirst(strtolower($kind))] ?? null;
          if (!$sql) throw new RuntimeException("Cannot read the definition of {$kind} {$r['ROUTINE_NAME']} (owned by another database user). Backup aborted so it is not incomplete.");
          $stream->writeLine(Canonical::encode(['t' => 'routine', 'kind' => $kind, 'name' => $r['ROUTINE_NAME'], 'sql' => self::stripDefiner($sql)]));
          $objects['routines']++;
        }

        // Tables with rows, then views, then triggers
        $list = $pdo->prepare('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
        $list->execute([$db]);
        $all = $list->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all as $t) {
          if ($t['TABLE_TYPE'] !== 'BASE TABLE') continue;
          $table = $t['TABLE_NAME'];
          $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
          $stream->writeLine(Canonical::encode(['t' => 'table', 'name' => $table, 'sql' => $create]));
          $objects['tables']++;

          $cols = array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
          $counts[$table] = 0;
          $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
          $stmt = $pdo->query("SELECT * FROM `{$table}`");
          $batch = [];
          while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
            $batch[] = array_map([self::class, 'encodeValue'], $row);
            if (count($batch) >= config('backup.batch_rows')) {
              $stream->writeLine(json_encode(['t' => 'rows', 'table' => $table, 'cols' => $cols, 'rows' => $batch], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
              $counts[$table] += count($batch);
              $batch = [];
            }
          }
          $stmt->closeCursor();
          $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
          if ($batch) {
            $stream->writeLine(json_encode(['t' => 'rows', 'table' => $table, 'cols' => $cols, 'rows' => $batch], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $counts[$table] += count($batch);
          }
        }

        foreach ($all as $t) {
          if ($t['TABLE_TYPE'] !== 'VIEW') continue;
          $create = $pdo->query("SHOW CREATE VIEW `{$t['TABLE_NAME']}`")->fetch(PDO::FETCH_ASSOC)['Create View'];
          $stream->writeLine(Canonical::encode(['t' => 'view', 'name' => $t['TABLE_NAME'], 'sql' => self::stripDefiner($create)]));
          $objects['views']++;
        }

        $trg = $pdo->prepare('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY TRIGGER_NAME');
        $trg->execute([$db]);
        foreach ($trg->fetchAll(PDO::FETCH_COLUMN) as $name2) {
          $sql = $pdo->query("SHOW CREATE TRIGGER `{$name2}`")->fetch(PDO::FETCH_ASSOC)['SQL Original Statement'] ?? null;
          if (!$sql) throw new RuntimeException("Cannot read trigger {$name2}. Backup aborted.");
          $stream->writeLine(Canonical::encode(['t' => 'trigger', 'name' => $name2, 'sql' => self::stripDefiner($sql)]));
          $objects['triggers']++;
        }

        $seals = [];
        if (in_array('event_closings', array_column($all, 'TABLE_NAME'), true)) {
          foreach ($pdo->query('SELECT sequence_no, payload_sha256 FROM event_closings ORDER BY sequence_no')->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $seals[(string) $s['sequence_no']] = $s['payload_sha256'];
          }
        }

        $stream->writeLine(Canonical::encode(['t' => 'end', 'counts' => $counts]));
        $stream->close();
        $pdo->exec('COMMIT');
      } catch (\Throwable $e) {
        try { $stream->close(); } catch (\Throwable) {}
        @unlink($path);
        throw $e;
      }

      $manifest = [
        'format' => config('backup.format'),
        'file' => basename($path),
        'sha256' => hash_file('sha256', $path),
        'size' => filesize($path),
        'type' => $type,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by_account_id' => $accountId,
        'server_id' => config('sealing.server_id'),
        'database' => $db,
        'active_event' => $active,
        'row_counts' => $counts,
        'objects' => $objects,
        'seals' => (object) $seals,
        'last_migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
        'key_fingerprint' => $this->keys->signingFingerprint(),
      ];
      $manifest['signature'] = base64_encode(sodium_crypto_sign_detached(Canonical::encode($manifest), $this->keys->signingSecret()));
      file_put_contents($this->dir() . "/{$name}.manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      chmod($path, 0400);

      $this->appLog($accountId, "Database backup created ({$type}): " . basename($path) . " sha256 {$manifest['sha256']}");
      return $manifest;
    });
  }

  // ================================================================== list

  public function list(): array
  {
    $out = [];
    foreach (glob($this->dir() . '/*.manifest.json') ?: [] as $mf) {
      try {
        $m = $this->loadManifest(basename($mf, '.manifest.json') . '.bak.enc');
        $m['_valid'] = true;
        $m['_error'] = null;
      } catch (\Throwable $e) {
        $m = json_decode((string) file_get_contents($mf), true) ?: ['file' => basename($mf)];
        $m['_valid'] = false;
        $m['_error'] = $e->getMessage();
      }
      $out[] = $m;
    }
    usort($out, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $out;
  }

  // ================================================================ verify

  /** Full read-through check. Changes nothing. */
  public function verify(string $file): array
  {
    @set_time_limit(0);
    $m = $this->loadManifest($file);
    $counts = [];
    $end = null;
    foreach (EncryptedStream::readLines($this->path($file), $this->keys->backupKey()) as $line) {
      $rec = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
      if ($rec['t'] === 'rows') $counts[$rec['table']] = ($counts[$rec['table']] ?? 0) + count($rec['rows']);
      if ($rec['t'] === 'table') $counts[$rec['name']] ??= 0;
      if ($rec['t'] === 'end') $end = $rec['counts'];
    }
    if ($end === null) throw new RuntimeException('Backup has no end marker.');
    if ($counts != $m['row_counts'] || $end != $m['row_counts']) throw new RuntimeException('Row counts in the backup differ from its manifest.');
    return ['file' => $file, 'rows' => array_sum($counts), 'tables' => count($counts), 'manifest' => $m];
  }

  // =============================================================== restore

  /**
   * @param array{account_id:int, approved_by:?int, reason:string, allow_unsealing?:bool, source:string} $ctx
   */
  public function restore(string $file, array $ctx): array
  {
    @set_time_limit(0);
    $started = microtime(true);
    $m = $this->loadManifest($file);
    $this->verify($file); // full decrypt + count check before touching anything

    // Refuse backups that would un-seal events sealed after the backup was taken.
    $registry = EventSealer::registry();
    $backupSeals = (array) ($m['seals'] ?? []);
    $lost = [];
    foreach ($registry as $seq => $hash) {
      if (($backupSeals[(string) $seq] ?? null) !== $hash) $lost[] = 'S' . sprintf('%04d', $seq);
    }
    if ($lost && empty($ctx['allow_unsealing'])) {
      throw new RuntimeException('This backup predates sealed closing(s) ' . implode(', ', $lost)
        . '. Restoring it would remove those seals. Choose a newer backup.');
    }

    $journal = [
      'at' => date('Y-m-d H:i:s'), 'file' => $file, 'backup_sha256' => $m['sha256'],
      'account_id' => $ctx['account_id'], 'approved_by' => $ctx['approved_by'], 'reason' => $ctx['reason'],
      'source' => $ctx['source'], 'allow_unsealing' => !empty($ctx['allow_unsealing']), 'lost_seals' => $lost,
    ];

    Artisan::call('down', ['--retry' => 30]);
    try {
      $pre = $this->create('prerestore', $ctx['account_id']);
      $journal['prerestore_backup'] = $pre['file'];

      try {
        $counts = $this->withLock(fn () => $this->apply($this->path($file)));
        if ($counts != $m['row_counts']) throw new RuntimeException('Restored row counts differ from the backup manifest.');
        $this->assertSealsIntact();
      } catch (\Throwable $e) {
        // Put the database back exactly as it was before this restore.
        $journal['error'] = $e->getMessage();
        try {
          $this->withLock(fn () => $this->apply($this->path($pre['file'])));
          $journal['result'] = 'failed_rolled_back';
        } catch (\Throwable $e2) {
          $journal['result'] = 'failed_rollback_failed';
          $journal['rollback_error'] = $e2->getMessage();
        }
        $this->journal($journal);
        throw new RuntimeException("Restore failed and was rolled back to the pre-restore backup ({$pre['file']}): " . $e->getMessage(), 0, $e);
      }

      $journal['result'] = 'restored';
      $journal['seconds'] = round(microtime(true) - $started, 1);
      $this->journal($journal);
      $this->appLog($ctx['account_id'], "Database RESTORED from {$file} (sha256 {$m['sha256']}) via {$ctx['source']}. Reason: {$ctx['reason']}. Approved by account #"
        . ($ctx['approved_by'] ?? 'n/a') . ". Pre-restore backup: {$pre['file']}");

      return ['file' => $file, 'prerestore_backup' => $pre['file'], 'rows' => array_sum($counts), 'seconds' => $journal['seconds'], 'lost_seals' => $lost];
    } finally {
      Artisan::call('up');
    }
  }

  /** Drops every object in the database and rebuilds it from a backup stream. */
  protected function apply(string $path): array
  {
    $pdo = $this->pdo();
    $db = config('database.connections.mysql.database');
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0, UNIQUE_CHECKS=0, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

    $lines = EncryptedStream::readLines($path, $this->keys->backupKey());
    $header = json_decode($lines->current(), true);
    if (($header['t'] ?? null) !== 'header' || ($header['format'] ?? null) !== config('backup.format')) {
      throw new RuntimeException('Unsupported backup format.');
    }
    $lines->next();

    $this->dropAll($pdo, $db);

    $counts = [];
    $views = [];
    $triggers = [];
    for (; $lines->valid(); $lines->next()) {
      $rec = json_decode($lines->current(), true, 512, JSON_THROW_ON_ERROR);
      switch ($rec['t']) {
        case 'routine':
        case 'table':
          $pdo->exec($rec['sql']);
          if ($rec['t'] === 'table') $counts[$rec['name']] = 0;
          break;
        case 'rows':
          $this->insertRows($pdo, $rec['table'], $rec['cols'], $rec['rows']);
          $counts[$rec['table']] += count($rec['rows']);
          break;
        case 'view':
          $views[$rec['name']] = $rec['sql'];
          break;
        case 'trigger':
          $triggers[] = $rec['sql'];
          break;
      }
    }

    // Views may depend on other views: create in passes until all succeed.
    for ($pass = 0; $views && $pass < 10; $pass++) {
      foreach ($views as $name => $sql) {
        try { $pdo->exec($sql); unset($views[$name]); } catch (\PDOException) {}
      }
    }
    if ($views) throw new RuntimeException('Could not recreate views: ' . implode(', ', array_keys($views)));

    foreach ($triggers as $sql) $pdo->exec($sql);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1, UNIQUE_CHECKS=1');
    return $counts;
  }

  private function dropAll(PDO $pdo, string $db): void
  {
    $q = fn (string $sql) => ($s = $pdo->prepare($sql)) && $s->execute([$db]) ? $s->fetchAll(PDO::FETCH_NUM) : [];
    foreach ($q('SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ?') as [$v]) $pdo->exec("DROP VIEW IF EXISTS `{$v}`");
    foreach ($q('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?') as [$t]) $pdo->exec("DROP TRIGGER IF EXISTS `{$t}`");
    foreach ($q('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?') as [$r, $k]) $pdo->exec("DROP {$k} IF EXISTS `{$r}`");
    foreach ($q("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'") as [$t]) $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
  }

  private function insertRows(PDO $pdo, string $table, array $cols, array $rows): void
  {
    $colSql = implode(',', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', $cols));
    $one = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . "` ({$colSql}) VALUES " . implode(',', array_fill(0, count($rows), $one));
    $values = [];
    foreach ($rows as $row) {
      if (count($row) !== count($cols)) throw new RuntimeException("Malformed row in {$table}.");
      foreach ($row as $v) $values[] = self::decodeValue($v);
    }
    $pdo->prepare($sql)->execute($values);
  }

  /** After restore, every sealed closing must still carry a valid signature. */
  private function assertSealsIntact(): void
  {
    if (!DB::getSchemaBuilder()->hasTable('event_closings') || !is_file($this->keys->path('sign.pub'))) return;
    $pub = $this->keys->signingPublic();
    foreach (EventClosing::orderBy('sequence_no')->get() as $c) {
      if (hash('sha256', $c->payload) !== $c->payload_sha256
        || !sodium_crypto_sign_verify_detached(base64_decode($c->signature), $c->payload, $pub)) {
        throw new RuntimeException("Sealed closing S" . sprintf('%04d', $c->sequence_no) . " failed signature check after restore.");
      }
    }
  }

  // ============================================= raw SQL import (TESTING)

  public function rawImportAllowed(): bool
  {
    return (bool) config('backup.allow_raw_sql_import') && !app()->environment('production');
  }

  public function importDir(): string
  {
    $dir = $this->dir() . '/import';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    return $dir;
  }

  public function importFiles(): array
  {
    $out = [];
    foreach (array_merge(glob($this->importDir() . '/*.sql') ?: [], glob($this->importDir() . '/*.sql.gz') ?: []) as $f) {
      $out[] = ['file' => basename($f), 'size' => filesize($f), 'modified' => date('Y-m-d H:i:s', filemtime($f)), 'valid_name' => $this->isValidImportName(basename($f))];
    }
    usort($out, fn ($a, $b) => strcmp($b['modified'], $a['modified']));
    return $out;
  }

  /** Parses and whitelists every statement. Changes nothing. */
  public function checkSqlDump(string $file): array
  {
    $this->assertRawImportAllowed();
    @set_time_limit(0);
    $path = $this->importPath($file);
    $stats = SqlDumpReader::validate(SqlDumpReader::read($path), config('database.connections.mysql.database'));
    return $stats + ['file' => $file, 'sha256' => hash_file('sha256', $path), 'size' => filesize($path)];
  }

  /**
   * TESTING ONLY: replace the database with a raw .sql dump. Same safeguards as
   * restore(): validation first, maintenance mode, pre-restore backup,
   * automatic rollback on any failure, seal protection, journal.
   */
  public function restoreSqlDump(string $file, array $ctx): array
  {
    $this->assertRawImportAllowed();
    @set_time_limit(0);
    $started = microtime(true);
    $path = $this->importPath($file);
    $db = config('database.connections.mysql.database');
    $sql = SqlDumpReader::read($path);
    $stats = SqlDumpReader::validate($sql, $db); // nothing runs unless every statement passes

    $journal = [
      'at' => date('Y-m-d H:i:s'), 'file' => "import/{$file}", 'backup_sha256' => hash_file('sha256', $path),
      'kind' => 'raw_sql_import_TESTING', 'account_id' => $ctx['account_id'], 'approved_by' => $ctx['approved_by'],
      'reason' => $ctx['reason'], 'source' => $ctx['source'], 'allow_unsealing' => !empty($ctx['allow_unsealing']),
    ];

    Artisan::call('down', ['--retry' => 30]);
    try {
      $pre = $this->create('prerestore', $ctx['account_id']);
      $journal['prerestore_backup'] = $pre['file'];

      try {
        $this->withLock(function () use ($sql, $db) {
          $pdo = $this->pdo();
          $pdo->exec("SET FOREIGN_KEY_CHECKS=0, UNIQUE_CHECKS=0, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
          $this->dropAll($pdo, $db);
          foreach (SqlDumpReader::statements($sql) as $stmt) {
            if (SqlDumpReader::isSameDatabaseSelect($stmt, $db)) continue;
            $pdo->exec($stmt);
          }
          $pdo->exec('SET FOREIGN_KEY_CHECKS=1, UNIQUE_CHECKS=1');
        });

        // Bring an older dump up to the current schema (event_closings, function ownership, ...).
        DB::purge();
        Artisan::call('migrate', ['--force' => true]);
        $journal['migrate_output'] = trim(Artisan::output());

        // Seals recorded outside the database must still be present after the import.
        $inDb = DB::getSchemaBuilder()->hasTable('event_closings')
          ? DB::table('event_closings')->pluck('payload_sha256', 'sequence_no')->all() : [];
        $lost = [];
        foreach (EventSealer::registry() as $seq => $hash) {
          if (($inDb[$seq] ?? null) !== $hash) $lost[] = 'S' . sprintf('%04d', $seq);
        }
        $journal['lost_seals'] = $lost;
        if ($lost && empty($ctx['allow_unsealing'])) {
          throw new RuntimeException('The dump does not contain sealed closing(s) ' . implode(', ', $lost) . '; importing it would un-seal those events.');
        }
        $this->assertSealsIntact();
      } catch (\Throwable $e) {
        $journal['error'] = $e->getMessage();
        try {
          $this->withLock(fn () => $this->apply($this->path($pre['file'])));
          DB::purge();
          $journal['result'] = 'failed_rolled_back';
        } catch (\Throwable $e2) {
          $journal['result'] = 'failed_rollback_failed';
          $journal['rollback_error'] = $e2->getMessage();
        }
        $this->journal($journal);
        throw new RuntimeException("Import failed and was rolled back to the pre-import backup ({$pre['file']}): " . $e->getMessage(), 0, $e);
      }

      $rows = 0;
      foreach (DB::select("SELECT TABLE_NAME n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'", [$db]) as $t) {
        $rows += DB::table($t->n)->count();
      }
      $journal['result'] = 'imported';
      $journal['seconds'] = round(microtime(true) - $started, 1);
      $this->journal($journal);
      $this->appLog($ctx['account_id'], "TESTING: database replaced by RAW SQL import import/{$file} (sha256 {$journal['backup_sha256']}) via {$ctx['source']}. Reason: {$ctx['reason']}. Pre-import backup: {$pre['file']}");

      return ['file' => $file, 'prerestore_backup' => $pre['file'], 'rows' => $rows, 'seconds' => $journal['seconds'], 'statements' => $stats['statements']];
    } finally {
      Artisan::call('up');
    }
  }

  private function assertRawImportAllowed(): void
  {
    if (!$this->rawImportAllowed()) {
      throw new RuntimeException('Raw SQL import is disabled. It is for testing only (BACKUP_ALLOW_RAW_SQL_IMPORT=true) and never allowed in production.');
    }
  }

  private function isValidImportName(string $file): bool
  {
    return (bool) preg_match('/^[A-Za-z0-9_.-]+\.sql(\.gz)?$/', $file) && !str_contains($file, '..');
  }

  private function importPath(string $file): string
  {
    if (!$this->isValidImportName($file)) {
      throw new RuntimeException('Invalid file name. Use only letters, numbers, dot, dash and underscore, ending in .sql or .sql.gz.');
    }
    $path = $this->importDir() . '/' . $file;
    if (!is_file($path)) throw new RuntimeException('File not found in the import folder.');
    return $path;
  }

  // =============================================================== helpers

  public function loadManifest(string $file): array
  {
    $path = $this->path($file);
    $mfPath = substr($path, 0, -strlen('.bak.enc')) . '.manifest.json';
    if (!is_file($path) || !is_file($mfPath)) throw new RuntimeException('Backup or manifest not found.');

    $m = json_decode(file_get_contents($mfPath), true);
    if (!is_array($m) || empty($m['signature'])) throw new RuntimeException('Manifest is unreadable.');
    $sig = base64_decode($m['signature']);
    unset($m['signature']);
    if (isset($m['seals']) && $m['seals'] === []) $m['seals'] = (object) [];

    if (($m['key_fingerprint'] ?? '') !== $this->keys->signingFingerprint()
      || !sodium_crypto_sign_verify_detached($sig, Canonical::encode($m), $this->keys->signingPublic())) {
      throw new RuntimeException('Manifest signature is invalid: not created by this server, or modified.');
    }
    if (hash_file('sha256', $path) !== $m['sha256']) throw new RuntimeException('Backup file hash does not match its manifest (file altered or corrupted).');
    $m['seals'] = (array) $m['seals'];
    return $m;
  }

  /** Resolves a backup file name safely inside the backup directory. */
  public function path(string $file): string
  {
    if (!preg_match('/^[A-Za-z0-9_.-]+\.bak\.enc$/', $file) || str_contains($file, '..')) {
      throw new RuntimeException('Invalid backup file name.');
    }
    return $this->dir() . '/' . $file;
  }

  public function dir(): string
  {
    $dir = rtrim(config('backup.path'), '/');
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    return $dir;
  }

  public function journalPath(): string
  {
    return $this->dir() . '/restore-journal.jsonl';
  }

  public function journalEntries(): array
  {
    if (!is_file($this->journalPath())) return [];
    return array_reverse(array_map(fn ($l) => json_decode($l, true),
      file($this->journalPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
  }

  /** Append-only, outside the database, so it survives the restore it describes. */
  private function journal(array $entry): void
  {
    file_put_contents($this->journalPath(), json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    @chmod($this->journalPath(), 0600);
  }

  private function withLock(callable $fn)
  {
    $fh = fopen($this->dir() . '/.lock', 'c');
    if (!flock($fh, LOCK_EX | LOCK_NB)) {
      fclose($fh);
      throw new RuntimeException('Another backup or restore is already running.');
    }
    try {
      return $fn();
    } finally {
      flock($fh, LOCK_UN);
      fclose($fh);
    }
  }

  private function pdo(): PDO
  {
    $c = config('database.connections.mysql');
    return new PDO(
      "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",
      $c['username'], $c['password'],
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
    );
  }

  private function appLog(?int $accountId, string $message): void
  {
    DB::table('transactions')->insert([
      'account_id' => $accountId ?? -1,
      'transaction_type' => 'Web App',
      'transaction_message' => $message,
      'transaction_datetime' => date('Y-m-d H:i:s'),
    ]);
  }

  private static function stripDefiner(string $sql): string
  {
    return preg_replace('/\sDEFINER\s*=\s*`[^`]*`@`[^`]*`/', '', $sql);
  }

  private static function encodeValue($v)
  {
    return is_string($v) && !mb_check_encoding($v, 'UTF-8') ? ['b64' => base64_encode($v)] : $v;
  }

  private static function decodeValue($v)
  {
    return is_array($v) ? base64_decode($v['b64']) : $v;
  }
}
