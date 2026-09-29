<?php

namespace App\Services\Closing;

use Illuminate\Support\Facades\DB;

/**
 * Builds the closing-report payload for one event from the live database.
 *
 * All money is handled as integer centavos and emitted as fixed 2-decimal
 * strings, so the payload hashes identically on every machine.
 *
 * The payload deliberately contains NO personal data: tellers appear only as
 * codes (T01, T02...) and internal ids. Names are resolved locally for printing.
 */
class ClosingReportBuilder
{
  private const SETTLED = ['Completed', 'Draw', 'Cancelled'];

  /** Database connection to read from (null = the live database). */
  private ?string $connection = null;

  /** Returns a builder that reads from another connection (legacy backups). */
  public function on(?string $connection): static
  {
    $clone = clone $this;
    $clone->connection = $connection;
    return $clone;
  }

  private function db(): \Illuminate\Database\Connection
  {
    return DB::connection($this->connection);
  }

  public function build(int $eventId, int $logFromId, int $logToId): array
  {
    $event = $this->db()->table('events')->where('event_id', $eventId)->first();
    if (!$event) {
      throw new \InvalidArgumentException("Event #{$eventId} not found.");
    }

    $matches = $this->db()->table('matches')->where('event_id', $eventId)->orderBy('match_number')->orderBy('match_id')->get();
    $matchIds = $matches->pluck('match_id')->all();

    $bets = $this->db()->table('bets')->whereIn('match_id', $matchIds ?: [0])->orderBy('bet_id')->get();
    $claims = $this->db()->table('claims')->whereIn('bet_id', $bets->pluck('bet_id')->all() ?: [0])->orderBy('claim_id')->get();

    $eventTellers = $this->db()->table('event_tellers')->where('event_id', $eventId)->orderBy('event_teller_id')->get();
    $etIds = $eventTellers->pluck('event_teller_id')->all() ?: [0];
    $cashIns = $this->db()->table('cash_ins')->whereIn('event_teller_id', $etIds)->orderBy('cash_in_id')->get();
    $cashOuts = $this->db()->table('cash_outs')->whereIn('event_teller_id', $etIds)->orderBy('cash_out_id')->get();
    $remittances = $this->db()->table('teller_remittances')->whereIn('event_teller_id', $etIds)->orderBy('teller_remittance_id')->get();
    $shorts = $this->db()->table('teller_shorts')
      ->whereIn('teller_remittance_id', $remittances->pluck('teller_remittance_id')->all() ?: [0])
      ->orderBy('teller_short_id')->get();

    $logs = $this->db()->table('transactions')
      ->whereBetween('transaction_id', [$logFromId, $logToId])
      ->orderBy('transaction_id')->get();

    $rate = (string) $event->event_percentage;
    $fightAudit = $this->fightAudit($logs, $eventId);
    $usernames = $this->db()->table('accounts')->pluck('username', 'account_id');

    // ---------------------------------------------------------------- fights
    $betsByMatch = $bets->groupBy('match_id');
    $paidByBet = [];
    foreach ($claims as $c) {
      $paidByBet[$c->bet_id] = ($paidByBet[$c->bet_id] ?? 0) + Money::cents($c->claim_amount);
    }

    $fights = [];
    $flags = ['match_total_mismatch' => [], 'payout_mismatch' => [], 'refund_mismatch' => [], 'overpaid' => [], 'breakage_out_of_bounds' => []];

    foreach ($matches as $m) {
      $mb = $betsByMatch->get($m->match_id, collect());
      $valid = $mb->where('bet_status', 1);
      $voided = $mb->where('bet_status', 2);

      $meron = $valid->where('bet_side', 'Meron')->sum(fn ($b) => Money::cents($b->bet_amount));
      $wala = $valid->where('bet_side', 'Wala')->sum(fn ($b) => Money::cents($b->bet_amount));
      $pool = $meron + $wala;
      $payable = $valid->sum(fn ($b) => Money::cents($b->bet_payout_amount));
      $paid = $valid->sum(fn ($b) => $paidByBet[$b->bet_id] ?? 0);

      $status = (string) $m->match_status;
      $winner = $status === 'Completed' ? (string) $m->match_winner : null;
      $commission = 0; $refunds = 0; $winnings = 0; $breakage = 0;

      if ($status === 'Completed') {
        $commission = Money::percentOf($pool, $rate);
        $winnings = $payable;
        $breakage = $pool - $commission - $winnings;

        // Independent recomputation per winning bet. Current code pays
        // floor(bet_amount * odds); earlier versions paid bet_amount * odds to
        // the centavo. Either is accepted; anything else is flagged.
        $odds = $winner === 'Meron' ? $m->meron_odds : $m->wala_odds;
        $winningBets = $valid->where('bet_side', $winner);
        $floored = $winningBets->sum(fn ($b) => Money::floorPesosTimesOdds($b->bet_amount, $odds));
        $exact = $winningBets->sum(fn ($b) => Money::centsTimesOdds($b->bet_amount, $odds));
        if ($winnings !== $floored && $winnings !== $exact) $flags['payout_mismatch'][] = (int) $m->match_number;

        // Floor rounding loses < 1 peso per winning bet; odds rounding (2 dp) adds <= 0.005 x side total.
        $sideTotal = $winner === 'Meron' ? $meron : $wala;
        $bound = $winningBets->count() * 100 + intdiv($sideTotal, 200) + 100;
        if (abs($breakage) > $bound) $flags['breakage_out_of_bounds'][] = (int) $m->match_number;
      } elseif (in_array($status, ['Draw', 'Cancelled'], true)) {
        $refunds = $payable;
        $breakage = $pool - $refunds;
        if ($refunds !== $pool) $flags['refund_mismatch'][] = (int) $m->match_number;
      }

      if (Money::cents($m->meron_total_bet) !== $meron || Money::cents($m->wala_total_bet) !== $wala) {
        $flags['match_total_mismatch'][] = (int) $m->match_number;
      }
      if ($paid > $payable) $flags['overpaid'][] = (int) $m->match_number;

      // Declaring a result auto-creates the next fight, so the last fight of
      // every event is an unsettled fight nobody bet on. A fight with no bets
      // at all (voided bets count) is reported as unused and does not block sealing.
      $unused = !in_array($status, self::SETTLED, true) && $mb->isEmpty();

      $audit = $fightAudit[(int) $m->match_number] ?? [];
      $fights[] = [
        'fight_no' => (int) $m->match_number,
        'match_id' => (int) $m->match_id,
        'status' => $status,
        'unused' => $unused,
        'winner' => $winner,
        'meron_odds' => $winner ? Money::odds($m->meron_odds) : null,
        'wala_odds' => $winner ? Money::odds($m->wala_odds) : null,
        'meron_total' => Money::fmt($meron),
        'wala_total' => Money::fmt($wala),
        'net_pool' => Money::fmt($pool),
        'bets_meron' => $valid->where('bet_side', 'Meron')->count(),
        'bets_wala' => $valid->where('bet_side', 'Wala')->count(),
        'voids_count' => $voided->count(),
        'voids_amount' => Money::fmt($voided->sum(fn ($b) => Money::cents($b->bet_amount))),
        'commission' => Money::fmt($commission),
        'refunds' => Money::fmt($refunds),
        'winnings' => Money::fmt($winnings),
        'breakage' => Money::fmt($breakage),
        // What the house actually retained: pool - winnings - refunds.
        'house_take' => Money::fmt($commission + $breakage),
        'payable' => Money::fmt($payable),
        'paid' => Money::fmt($paid),
        'unclaimed' => Money::fmt($payable - $paid),
        'audit' => [
          'first_bet_at' => optional($mb->min('bet_datetime'), fn ($v) => (string) $v),
          'last_bet_at' => optional($mb->max('bet_datetime'), fn ($v) => (string) $v),
          'betting_opened_at' => $audit['opened_at'] ?? null,
          'betting_closed_at' => $audit['closed_at'] ?? null,
          'betting_open_count' => $audit['open_count'] ?? 0,
          'declared_at' => $audit['declared_at'] ?? null,
          'declared_by' => isset($audit['declared_by']) ? ($usernames[$audit['declared_by']] ?? ('#' . $audit['declared_by'])) : null,
          'declaration_count' => $audit['declaration_count'] ?? 0,
          'result_corrections' => max(0, ($audit['declaration_count'] ?? 0) - 1),
        ],
      ];
    }

    // --------------------------------------------------------------- tellers
    $tellers = [];
    $balanceMismatch = [];
    $betsByTeller = $bets->groupBy('event_teller_id');
    $claimsByTeller = $claims->groupBy('event_teller_id');
    $remitByTeller = $remittances->groupBy('event_teller_id');
    $shortByRemit = $shorts->groupBy('teller_remittance_id');

    foreach ($eventTellers->values() as $i => $et) {
      $code = sprintf('T%02d', $i + 1);
      $tb = $betsByTeller->get($et->event_teller_id, collect());
      $ins = $cashIns->where('event_teller_id', $et->event_teller_id)->where('cash_in_status', 'Approved');
      $outs = $cashOuts->where('event_teller_id', $et->event_teller_id)->where('cash_out_status', 'Approved');

      $opening = $ins->where('cash_in_type', 'AdminCashInInit')->sum(fn ($r) => Money::cents($r->cash_in_amount));
      $cashIn = $ins->where('cash_in_type', '!=', 'AdminCashInInit')->sum(fn ($r) => Money::cents($r->cash_in_amount));
      $cashOut = $outs->sum(fn ($r) => Money::cents($r->cash_out_amount));
      $gross = $tb->sum(fn ($b) => Money::cents($b->bet_amount));
      $voids = $tb->where('bet_status', 2)->sum(fn ($b) => Money::cents($b->bet_amount));
      $net = $gross - $voids;
      $payouts = $claimsByTeller->get($et->event_teller_id, collect())->sum(fn ($c) => Money::cents($c->claim_amount));
      $expected = $opening + $cashIn + $net - $payouts - $cashOut;
      $system = Money::cents($et->teller_balance);
      if ($expected !== $system) $balanceMismatch[] = $code;

      $remits = $remitByTeller->get($et->event_teller_id, collect());
      $counted = null; $shortOver = null; $recordedShort = 0;
      if ($remits->isNotEmpty()) {
        $counted = $remits->sum(fn ($r) => $this->denominationsCents($r));
        $shortOver = $counted - $expected;
        foreach ($remits as $r) {
          $recordedShort += $shortByRemit->get($r->teller_remittance_id, collect())->sum(fn ($s) => Money::cents($s->short_amount));
        }
      }

      $tellers[] = [
        'teller_code' => $code,
        'event_teller_id' => (int) $et->event_teller_id,
        'opening_cash' => Money::fmt($opening),
        'cash_in' => Money::fmt($cashIn),
        'cash_out' => Money::fmt($cashOut),
        'gross_bets' => Money::fmt($gross),
        'voids' => Money::fmt($voids),
        'net_bets' => Money::fmt($net),
        'payouts' => Money::fmt($payouts),
        'expected_cash' => Money::fmt($expected),
        'system_balance' => Money::fmt($system),
        'counted_cash' => $counted === null ? null : Money::fmt($counted),
        'short_over' => $shortOver === null ? null : Money::fmt($shortOver),
        'recorded_shorts' => Money::fmt($recordedShort),
        'bet_count' => $tb->where('bet_status', 1)->count(),
        'void_count' => $tb->where('bet_status', 2)->count(),
        'payout_count' => $claimsByTeller->get($et->event_teller_id, collect())->count(),
      ];
    }

    // ---------------------------------------------------------------- totals
    $sum = fn (string $k, array $rows) => array_sum(array_map(fn ($r) => Money::cents($r[$k]), $rows));
    $grossBets = $bets->sum(fn ($b) => Money::cents($b->bet_amount));
    $voidsTotal = $bets->where('bet_status', 2)->sum(fn ($b) => Money::cents($b->bet_amount));
    $settled = array_filter($fights, fn ($f) => in_array($f['status'], self::SETTLED, true));

    $totals = [
      'fights_total' => count($fights),
      'fights_completed' => count(array_filter($fights, fn ($f) => $f['status'] === 'Completed')),
      'fights_draw' => count(array_filter($fights, fn ($f) => $f['status'] === 'Draw')),
      'fights_cancelled' => count(array_filter($fights, fn ($f) => $f['status'] === 'Cancelled')),
      'fights_unused' => count(array_filter($fights, fn ($f) => $f['unused'])),
      'fights_unsettled' => count(array_filter($fights, fn ($f) => !$f['unused'] && !in_array($f['status'], self::SETTLED, true))),
      'gross_bets' => Money::fmt($grossBets),
      'voided_bets' => Money::fmt($voidsTotal),
      'net_bets' => Money::fmt($grossBets - $voidsTotal),
      'refunds' => Money::fmt($sum('refunds', $fights)),
      'winnings' => Money::fmt($sum('winnings', $fights)),
      'commission' => Money::fmt($sum('commission', $fights)),
      'breakage' => Money::fmt($sum('breakage', $fights)),
      'house_take' => Money::fmt($sum('house_take', $fights)),
      'unsettled_pool' => Money::fmt($sum('net_pool', array_filter($fights, fn ($f) => !in_array($f['status'], self::SETTLED, true)))),
      'payable' => Money::fmt($sum('payable', $fights)),
      'paid' => Money::fmt($sum('paid', $fights)),
      'unclaimed' => Money::fmt($sum('unclaimed', $fights)),
      'teller_opening_cash' => Money::fmt($sum('opening_cash', $tellers)),
      'teller_cash_in' => Money::fmt($sum('cash_in', $tellers)),
      'teller_cash_out' => Money::fmt($sum('cash_out', $tellers)),
      'teller_payouts' => Money::fmt($sum('payouts', $tellers)),
      'bet_count' => $bets->where('bet_status', 1)->count(),
      'payout_count' => $claims->count(),
    ];

    // ------------------------------------------------------------ exceptions
    $claimCounts = $claims->countBy('bet_id')->filter(fn ($n) => $n > 1);
    $shortTellers = array_filter($tellers, fn ($t) => $t['short_over'] !== null && $t['short_over'] !== '0.00');
    $pendingCash = $cashIns->where('cash_in_status', 'Pending')->count() + $cashOuts->where('cash_out_status', 'Pending')->count();

    $exceptions = [
      'voids_count' => $bets->where('bet_status', 2)->count(),
      'voids_amount' => Money::fmt($voidsTotal),
      'result_corrections' => array_sum(array_map(fn ($f) => $f['audit']['result_corrections'], $fights)),
      'betting_reopens' => array_sum(array_map(fn ($f) => max(0, $f['audit']['betting_open_count'] - 1), $fights)),
      'reprints' => $logs->filter(fn ($l) => stripos((string) $l->transaction_message, 'reprint') !== false)->count(),
      'duplicate_claims' => $claimCounts->count(),
      'pending_cash_requests' => $pendingCash,
      'cash_short_over_tellers' => array_values(array_map(fn ($t) => $t['teller_code'], $shortTellers)),
      'teller_balance_mismatch' => $balanceMismatch,
    ] + array_map(fn ($v) => array_values(array_unique($v)), $flags);

    // ---------------------------------------------------------------- checks
    $netBets = $grossBets - $voidsTotal;
    $checks = [
      $this->check('all_fights_settled', $totals['fights_unsettled'] === 0,
        "{$totals['fights_unsettled']} unsettled fight(s) with bets: " . implode(',', array_map(fn ($f) => $f['fight_no'], array_filter($fights, fn ($f) => !$f['unused'] && !in_array($f['status'], self::SETTLED, true))))),
      $this->check('no_pending_cash_requests', $pendingCash === 0, "{$pendingCash} pending cash-in/out request(s)"),
      $this->check('net_bets_balance',
        $netBets === $sum('refunds', $fights) + $sum('winnings', $fights) + $sum('commission', $fights) + $sum('breakage', $fights) + Money::cents($totals['unsettled_pool']),
        'net bets = refunds + winnings + commission + breakage + unsettled pool'),
      $this->check('fights_equal_tellers', $sum('net_pool', $fights) === $sum('net_bets', $tellers), 'sum of fight pools = sum of teller net bets'),
      $this->check('payable_equals_paid_plus_unclaimed', Money::cents($totals['payable']) === Money::cents($totals['paid']) + Money::cents($totals['unclaimed']), 'payable = paid + unclaimed'),
      $this->check('teller_payouts_equal_claims', $sum('payouts', $tellers) === $claims->sum(fn ($c) => Money::cents($c->claim_amount)), 'teller payouts = total claims'),
      $this->check('no_duplicate_claims', $claimCounts->isEmpty(), $claimCounts->count() . ' receipt(s) paid more than once'),
      $this->check('no_overpaid_fights', empty($flags['overpaid']), 'paid more than payable (e.g. paid before a result correction) on fights: ' . implode(',', $flags['overpaid'])),
      $this->check('payouts_match_odds', empty($flags['payout_mismatch']), 'stored payouts differ from bet x odds on fights: ' . implode(',', $flags['payout_mismatch'])),
      $this->check('refunds_equal_pool', empty($flags['refund_mismatch']), 'fights: ' . implode(',', $flags['refund_mismatch'])),
      $this->check('breakage_within_bounds', empty($flags['breakage_out_of_bounds']), 'actual house take differs from pool x commission rate beyond rounding on fights: ' . implode(',', $flags['breakage_out_of_bounds'])),
      $this->check('match_totals_consistent', empty($flags['match_total_mismatch']), 'stored running totals differ from actual bets on fights: ' . implode(',', $flags['match_total_mismatch'])),
      $this->check('teller_balances_consistent', empty($balanceMismatch), 'tellers: ' . implode(',', $balanceMismatch)),
    ];

    // Teller ledger (where it exists): each teller's last ledger balance must equal their recorded balance.
    if ($this->db()->getSchemaBuilder()->hasTable('teller_ledger')) {
      $ledgerMismatch = [];
      $hasLedger = false;
      foreach ($tellers as $tl) {
        $last = $this->db()->table('teller_ledger')->where('event_teller_id', $tl['event_teller_id'])->orderByDesc('line_no')->value('balance_after');
        if ($last === null) continue;
        $hasLedger = true;
        if (Money::cents($last) !== Money::cents($tl['system_balance'])) $ledgerMismatch[] = $tl['teller_code'];
      }
      if ($hasLedger) {
        $checks[] = $this->check('teller_ledger_consistent', empty($ledgerMismatch), 'teller ledger does not end at the recorded balance for: ' . implode(',', $ledgerMismatch));
      }
    }

    return [
      'event' => [
        'event_id' => (int) $event->event_id,
        'name' => (string) $event->event_name,
        'date' => (string) $event->event_date,
        'commission_rate' => $rate,
      ],
      'fights' => $fights,
      'tellers' => $tellers,
      'totals' => $totals,
      'exceptions' => $exceptions,
      'checks' => $checks,
      '_detail_rows' => [
        'bets' => $bets, 'claims' => $claims, 'cash_ins' => $cashIns, 'cash_outs' => $cashOuts,
        'event_tellers' => $eventTellers, 'matches' => $matches, 'remittances' => $remittances,
        'shorts' => $shorts, 'logs' => $logs,
      ],
      '_log_range' => [$logFromId, $logToId],
    ];
  }

  /** Checks that must pass before an event may be sealed. */
  public static function blockingChecks(): array
  {
    return ['all_fights_settled', 'no_pending_cash_requests', 'no_duplicate_claims'];
  }

  private function check(string $name, bool $passed, string $detail): array
  {
    return ['name' => $name, 'passed' => $passed, 'detail' => $passed ? 'ok' : $detail];
  }

  private function denominationsCents(object $r): int
  {
    $pesos = 0;
    foreach ([1000, 500, 200, 100, 50, 20, 10, 5, 1] as $d) {
      $pesos += $d * (int) ($r->{"denom_{$d}"} ?? 0);
    }
    return $pesos * 100;
  }

  /**
   * Derives per-fight audit facts from the existing application log
   * (transactions table). Messages look like:
   *   "Matches - Fight #12 bet status updated to Open!{...json...}"
   *   "Matches - Fight #12 match status updated to Completed!{...json...}"
   */
  private function fightAudit($logs, int $eventId): array
  {
    $out = [];
    foreach ($logs as $l) {
      $msg = (string) $l->transaction_message;
      if (!preg_match('/^Matches - Fight #(\d+) (bet|match) status updated to (\w+)!/', $msg, $m)) continue;
      if (!preg_match('/"event_id":(\d+)/', $msg, $e) || (int) $e[1] !== $eventId) continue;

      $no = (int) $m[1];
      $a = &$out[$no];
      $a ??= ['open_count' => 0, 'declaration_count' => 0];
      $at = (string) $l->transaction_datetime;

      if ($m[2] === 'bet' && $m[3] === 'Open') {
        $a['open_count']++;
        $a['opened_at'] ??= $at;
      } elseif ($m[2] === 'bet' && $m[3] === 'Closed') {
        $a['closed_at'] = $at;
      } elseif ($m[2] === 'match' && in_array($m[3], self::SETTLED, true)) {
        $a['declaration_count']++;
        $a['declared_at'] = $at;
        $a['declared_by'] = $l->account_id;
      }
      unset($a);
    }
    return $out;
  }
}
