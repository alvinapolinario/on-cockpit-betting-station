<?php

namespace App\Http\Controllers;

use App\Services\Closing\Money;
use App\Services\Ledger\TellerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Teller account ledger screens (admins only; web routes under isAdmin).
 */
class TellerLedgerController extends Controller
{
  /** One teller's ledger for an event, with filters. */
  public function index(Request $r)
  {
    $events = DB::table('events')->orderByDesc('event_date')->orderByDesc('event_id')->get(['event_id', 'event_name', 'event_date', 'event_status']);
    $eventId = (int) ($r->query('event_id') ?: (DB::table('events')->where('event_status', 'Active')->value('event_id') ?: optional($events->first())->event_id));
    $tellers = $this->tellersOf($eventId);
    $etId = (int) $r->query('event_teller_id');
    if ($etId && !$tellers->contains('event_teller_id', $etId)) $etId = 0;

    $data = null;
    if ($etId) {
      $data = TellerLedger::verify($etId);
      $all = $data['lines'];
      $lines = $all
        ->when($r->filled('type'), fn ($c) => $c->where('type', $r->query('type')))
        ->when($r->filled('fight'), fn ($c) => $c->where('fight_no', (int) $r->query('fight')))
        ->when($r->filled('q'), fn ($c) => $c->filter(fn ($l) => stripos((string) $l->reference, trim($r->query('q'))) !== false));
      $data += [
        'filtered' => $lines->values(),
        'totals' => $this->totals($all),
        'teller' => $tellers->firstWhere('event_teller_id', $etId),
      ];
      $this->createLog(session()->get('account_id'), 'Web App', "Opened the Teller Ledger of event teller #{$etId}");
    }

    return view('teller-ledger.index', [
      'events' => $events, 'eventId' => $eventId, 'tellers' => $tellers, 'etId' => $etId, 'data' => $data,
      'names' => $this->names($eventId), 'accounts' => DB::table('accounts')->pluck('username', 'account_id'),
      'types' => TellerLedger::TYPES, 'filters' => $r->only(['type', 'fight', 'q']),
    ]);
  }

  /** Every teller of an event side by side. */
  public function all(Request $r)
  {
    $events = DB::table('events')->orderByDesc('event_date')->orderByDesc('event_id')->get(['event_id', 'event_name', 'event_date', 'event_status']);
    $eventId = (int) ($r->query('event_id') ?: (DB::table('events')->where('event_status', 'Active')->value('event_id') ?: optional($events->first())->event_id));
    $rows = $this->tellersOf($eventId)->map(function ($t) {
      $v = TellerLedger::verify((int) $t->event_teller_id);
      $last = $v['lines']->last();
      return (object) [
        'teller' => $t, 'lines' => $v['lines']->count(), 'totals' => $this->totals($v['lines']),
        'ledger_balance' => $last ? $last->balance_after : null, 'current_balance' => $t->teller_balance,
        'ok' => empty($v['problems']) && $v['ends_at_current_balance'], 'started_mid_event' => $v['started_mid_event'],
      ];
    });
    return view('teller-ledger.all', ['events' => $events, 'eventId' => $eventId, 'rows' => $rows, 'types' => TellerLedger::TYPES]);
  }

  /** Printable statement, signed by the teller at the end of the event. */
  public function print(int $event_teller_id)
  {
    $teller = DB::table('event_tellers')->join('tellers', 'tellers.teller_id', '=', 'event_tellers.teller_id')
      ->join('events', 'events.event_id', '=', 'event_tellers.event_id')
      ->where('event_tellers.event_teller_id', $event_teller_id)
      ->first(['event_tellers.*', 'tellers.teller_name', 'events.event_name', 'events.event_date', 'events.event_status']);
    abort_unless($teller, 404);

    $v = TellerLedger::verify($event_teller_id);
    $remit = DB::table('teller_remittances')->where('event_teller_id', $event_teller_id)->orderByDesc('teller_remittance_id')->first();
    $counted = null; $denoms = [];
    if ($remit) {
      $counted = 0;
      foreach ([1000, 500, 200, 100, 50, 20, 10, 5, 1] as $d) {
        $n = (int) ($remit->{"denom_{$d}"} ?? 0);
        $denoms[$d] = $n;
        $counted += $d * $n;
      }
    }
    $shorts = $remit ? DB::table('teller_shorts')->where('teller_remittance_id', $remit->teller_remittance_id)->get() : collect();
    $expected = $v['lines']->isNotEmpty() ? $v['lines']->last()->balance_after : $teller->teller_balance;

    $this->createLog(session()->get('account_id'), 'Web App', "Printed the Teller Ledger statement of event teller #{$event_teller_id}");

    return view('teller-ledger.print', [
      'teller' => $teller, 'v' => $v, 'totals' => $this->totals($v['lines']), 'types' => TellerLedger::TYPES,
      'names' => $this->names((int) $teller->event_id), 'accounts' => DB::table('accounts')->pluck('username', 'account_id'),
      'expected' => $expected, 'counted' => $counted, 'denoms' => $denoms, 'shorts' => $shorts,
      'printedBy' => DB::table('accounts')->where('account_id', session()->get('account_id'))->value('username'),
    ]);
  }

  private function tellersOf(int $eventId)
  {
    return DB::table('event_tellers')->join('tellers', 'tellers.teller_id', '=', 'event_tellers.teller_id')
      ->where('event_tellers.event_id', $eventId)->orderBy('tellers.teller_name')
      ->get(['event_tellers.event_teller_id', 'event_tellers.teller_balance', 'tellers.teller_name']);
  }

  /** event_teller_id => teller name, for "receipt issued by" on payouts. */
  private function names(int $eventId): array
  {
    return DB::table('event_tellers')->join('tellers', 'tellers.teller_id', '=', 'event_tellers.teller_id')
      ->where('event_tellers.event_id', $eventId)->pluck('tellers.teller_name', 'event_tellers.event_teller_id')->all();
  }

  /** Sum of in/out per entry type, in exact centavos. */
  private function totals($lines): array
  {
    $t = [];
    foreach ($lines as $l) {
      $t[$l->type]['count'] = ($t[$l->type]['count'] ?? 0) + 1;
      $t[$l->type]['in'] = ($t[$l->type]['in'] ?? 0) + Money::cents($l->amount_in);
      $t[$l->type]['out'] = ($t[$l->type]['out'] ?? 0) + Money::cents($l->amount_out);
    }
    foreach ($t as &$x) { $x['in'] = Money::fmt($x['in']); $x['out'] = Money::fmt($x['out']); }
    return $t;
  }
}
