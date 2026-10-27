<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Clears the betting data of finished events so the next event starts on small tables.
 *
 * Only data of SEALED events may be cleared: their closing payload (what the BIR system
 * receives) is kept in event_closings, which is never touched, so their packages can still
 * be downloaded. Events, closings, tellers, accounts and settings stay. A signed, encrypted
 * backup is taken first and can be restored from the Backups page.
 */
class DataReset
{
  /** Emptied, in this order. */
  public const TRUNCATE = [
    'bets',
    'bet_reprints',
    'cash_ins',
    'cash_outs',
    'claims',
    'matches',
    'event_tellers',
    'teller_ledger',
    'teller_shorts',
    'teller_remittances',
    'salaries',
    'personal_access_tokens',
    'bridge_inbox',
    'bridge_outbox',
    'transactions',
  ];

  public function __construct(private DatabaseBackup $backups) {}

  /** The passcode is configured (RESET_PASSCODE, plain or a bcrypt hash) and long enough. */
  public function enabled(): bool
  {
    $code = (string) config('backup.reset_passcode', '');
    return $this->isHash($code) || mb_strlen($code) >= 6;
  }

  public function passcodeMatches(string $given): bool
  {
    $code = (string) config('backup.reset_passcode', '');
    if (!$this->enabled() || $given === '') {
      return false;
    }
    return $this->isHash($code) ? Hash::check($given, $code) : hash_equals($code, $given);
  }

  /** Events that still have fights or tellers but are not sealed: their data must not be lost. */
  public function unsealedEvents(): array
  {
    return DB::table('events')
      ->where(function ($q) {
        $q->whereIn('event_id', DB::table('matches')->select('event_id'))
          ->orWhereIn('event_id', DB::table('event_tellers')->select('event_id'));
      })
      ->whereNotIn('event_id', DB::table('event_closings')->select('event_id'))
      ->orderBy('event_id')
      ->get(['event_id', 'event_name', 'event_status'])
      ->all();
  }

  /** Row counts that a reset would remove. */
  public function counts(): array
  {
    $counts = [];
    foreach (self::TRUNCATE as $table) {
      if (Schema::hasTable($table)) {
        $counts[$table] = DB::table($table)->count();
      }
    }
    return $counts;
  }

  /**
   * Backup first, then clear. Returns the backup manifest and the removed row counts.
   */
  public function run(int $accountId): array
  {
    if ($blocked = $this->unsealedEvents()) {
      $names = implode(', ', array_map(fn ($e) => "#{$e->event_id} {$e->event_name}", $blocked));
      throw new RuntimeException("Reset refused: these events are not sealed yet: {$names}. Seal them first (or reset before assigning tellers to a new event).");
    }

    $backup = $this->backups->create('prereset', $accountId);
    $counts = $this->counts();

    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
      foreach (array_keys($counts) as $table) {
        DB::statement("TRUNCATE TABLE `{$table}`");
      }
      DB::table('tellers')->update(['phone_uid' => '']);
    } finally {
      DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    return ['backup' => $backup, 'counts' => $counts];
  }

  private function isHash(string $code): bool
  {
    return str_starts_with($code, '$2y$') || str_starts_with($code, '$argon2');
  }
}
