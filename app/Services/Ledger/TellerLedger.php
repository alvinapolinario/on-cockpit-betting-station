<?php

namespace App\Services\Ledger;

use App\Services\Closing\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Teller account ledger: one append-only line per cash movement, with the
 * teller's cash on hand before and after it.
 *
 * record() must be called INSIDE the database transaction that changed the
 * teller's balance, AFTER the change. It locks the teller row, so concurrent
 * movements for the same teller are logged one after another with consecutive
 * balances, and the line is committed (or rolled back) together with the money.
 */
class TellerLedger
{
  public const TYPES = [
    'opening' => 'Opening cash',
    'bet' => 'Bet',
    'void' => 'Void',
    'payout_win' => 'Payout – win',
    'payout_refund' => 'Payout – refund',
    'cash_in' => 'Cash-in',
    'cash_out' => 'Cash-out',
    'cash_declined' => 'Cash request declined',
    'adjustment' => 'Adjustment',
  ];

  /**
   * @param array{fight_no?:int|null, match_id?:int|null, reference?:string|null, related_event_teller_id?:int|null,
   *              approved_by?:int|null, note?:string|null, source_table?:string|null, source_id?:int|null} $meta
   */
  public static function record(int $eventTellerId, string $type, $in, $out, array $meta = []): void
  {
    if (!isset(self::TYPES[$type])) throw new RuntimeException("Unknown ledger entry type {$type}.");
    if (DB::transactionLevel() < 1) throw new RuntimeException('Ledger lines must be written inside the money transaction.');

    $teller = DB::table('event_tellers')->where('event_teller_id', $eventTellerId)->lockForUpdate()->first(['event_id', 'teller_balance']);
    if (!$teller) throw new RuntimeException("Teller #{$eventTellerId} not found for the ledger.");

    $inC = Money::cents($in);
    $outC = Money::cents($out);
    $after = Money::cents($teller->teller_balance);
    $before = $after - $inC + $outC;
    $lineNo = (int) DB::table('teller_ledger')->where('event_teller_id', $eventTellerId)->max('line_no') + 1;

    DB::table('teller_ledger')->insert([
      'event_id' => $teller->event_id,
      'event_teller_id' => $eventTellerId,
      'line_no' => $lineNo,
      'entry_at' => date('Y-m-d H:i:s'),
      'type' => $type,
      'fight_no' => $meta['fight_no'] ?? null,
      'match_id' => $meta['match_id'] ?? null,
      'reference' => isset($meta['reference']) ? substr((string) $meta['reference'], 0, 60) : null,
      'amount_in' => Money::fmt($inC),
      'amount_out' => Money::fmt($outC),
      'balance_before' => Money::fmt($before),
      'balance_after' => Money::fmt($after),
      'related_event_teller_id' => $meta['related_event_teller_id'] ?? null,
      'recorded_by_account_id' => self::actor(),
      'approved_by_account_id' => $meta['approved_by'] ?? null,
      'note' => isset($meta['note']) ? substr((string) $meta['note'], 0, 255) : null,
      'source_table' => $meta['source_table'] ?? null,
      'source_id' => $meta['source_id'] ?? null,
    ]);
  }

  /**
   * A cash-in / cash-out has been approved or declined (or created already approved).
   * Stamps the approval time and logs the ledger line. Call after the balance change.
   */
  public static function cashDecision(string $direction, object $request, string $status): void
  {
    $isIn = $direction === 'in';
    $table = $isIn ? 'cash_ins' : 'cash_outs';
    $idCol = $isIn ? 'cash_in_id' : 'cash_out_id';
    $id = (int) $request->{$idCol};
    $amount = $isIn ? $request->cash_in_amount : $request->cash_out_amount;

    DB::table($table)->where($idCol, $id)->whereNull('approved_at')->update(['approved_at' => date('Y-m-d H:i:s')]);

    $approver = self::actor();
    $reference = ($isIn ? 'CI-' : 'CO-') . $id;
    $requested = $isIn ? ($request->cash_in_datetime ?? null) : ($request->cash_out_datetime ?? null);

    if ($status === 'Approved') {
      $opening = $isIn && ($request->cash_in_type ?? '') === 'AdminCashInInit';
      self::record((int) $request->event_teller_id, $opening ? 'opening' : ($isIn ? 'cash_in' : 'cash_out'),
        $isIn ? $amount : 0, $isIn ? 0 : $amount, [
          'reference' => $reference, 'approved_by' => $approver, 'source_table' => $table, 'source_id' => $id,
          'note' => $requested ? "requested {$requested}" : null,
        ]);
    } else {
      self::record((int) $request->event_teller_id, 'cash_declined', 0, 0, [
        'reference' => $reference, 'approved_by' => $approver, 'source_table' => $table, 'source_id' => $id,
        'note' => ($isIn ? 'Cash-in' : 'Cash-out') . ' of ' . number_format((float) $amount, 2) . ' declined; no cash moved',
      ]);
    }
  }

  /** Signed-in admin (web) or the account behind the app token (API). */
  public static function actor(): ?int
  {
    $id = session()->get('account_id') ?? request()->attributes->get('account_id');
    return $id === null ? null : (int) $id;
  }

  /**
   * Re-checks a teller's ledger. Returns the lines plus any problems:
   * each line's after = before + in - out; each before = previous after;
   * last after = the teller's current balance.
   */
  public static function verify(int $eventTellerId): array
  {
    $lines = DB::table('teller_ledger')->where('event_teller_id', $eventTellerId)->orderBy('line_no')->get();
    $problems = [];
    $prev = null;
    foreach ($lines as $l) {
      $b = Money::cents($l->balance_before);
      $a = Money::cents($l->balance_after);
      if ($a !== $b + Money::cents($l->amount_in) - Money::cents($l->amount_out)) {
        $problems[$l->line_no][] = 'after ≠ before + in − out';
      }
      if ($prev !== null && $b !== $prev) {
        $problems[$l->line_no][] = 'before ≠ previous line’s after (' . Money::fmt($prev) . ')';
      }
      $prev = $a;
    }
    $current = DB::table('event_tellers')->where('event_teller_id', $eventTellerId)->value('teller_balance');
    $endOk = $current === null || $prev === null || Money::cents($current) === $prev;
    return [
      'lines' => $lines,
      'problems' => $problems,
      'ends_at_current_balance' => $endOk,
      'current_balance' => $current,
      'started_mid_event' => $lines->isNotEmpty() && $lines->first()->type !== 'opening',
    ];
  }
}
