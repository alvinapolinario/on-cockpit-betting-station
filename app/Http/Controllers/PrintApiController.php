<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only data for the print service (teller statements, cash summaries and the
 * analytics snapshots behind /print/analytics). Guarded by the print.key middleware.
 */
class PrintApiController extends Controller
{
  private const TELLER_COLUMNS = [
    'event_teller_id',
    'event_id',
    'teller_id',
    'teller_name',
    'teller_balance',
    'event_name',
    'event_status',
    'event_date',
  ];

  public function events()
  {
    return Event::all();
  }

  public function tellers(Request $request)
  {
    $eventId = $request->query('event_id');
    if (!$eventId) {
      $event = Event::where('event_status', 'Active')->first();
      if (!$event) {
        return response()->json(['message' => 'No active event'], 404);
      }
      $eventId = $event->event_id;
    }

    $event = Event::where('event_id', $eventId)->first();
    if (!$event) {
      return response()->json(['message' => 'Event not found'], 404);
    }

    $tellers = DB::table('event_tellers_view')
      ->select(array_merge(self::TELLER_COLUMNS, ['teller_match_balance']))
      ->where('event_id', $eventId)
      ->orderBy('teller_name', 'asc')
      ->get();

    return response()->json([
      'event' => $event,
      'tellers' => $this->withCashSummaries($tellers),
    ]);
  }

  public function history(Request $request, $eventTellerId)
  {
    $matches = DB::table('bets')
      ->join('matches', 'bets.match_id', '=', 'matches.match_id')
      ->select(
        'matches.match_id',
        'matches.match_winner',
        'matches.meron_odds',
        'matches.wala_odds',
        'matches.match_status',
        'matches.match_number',
        DB::raw('SUM(CASE WHEN bets.bet_side = "Meron" AND bets.bet_status = 1 AND matches.match_status = "Completed" THEN bets.bet_amount ELSE 0 END) as total_meron'),
        DB::raw('SUM(CASE WHEN bets.bet_side = "Wala" AND bets.bet_status = 1 AND matches.match_status = "Completed" THEN bets.bet_amount ELSE 0 END) as total_wala'),
        DB::raw('SUM(CASE WHEN bets.bet_status = 1 AND matches.match_status = "Completed" THEN bets.bet_amount ELSE 0 END) as total_bet')
      )
      ->where('bets.event_teller_id', $eventTellerId)
      ->groupBy(
        'matches.match_id',
        'matches.match_winner',
        'matches.meron_odds',
        'matches.wala_odds',
        'matches.match_status',
        'matches.match_number'
      )
      ->orderBy('matches.match_number', 'desc')
      ->get();

    $fights = [];
    foreach ($matches as $match) {
      $fights[] = [
        'match_status' => $match->match_status,
        'match_winner' => $match->match_winner,
        'wala_odds' => $match->wala_odds,
        'meron_odds' => $match->meron_odds,
        'match_number' => $match->match_number,
        'total_meron' => $match->total_meron,
        'total_wala' => $match->total_wala,
        'total_bet' => $match->total_bet,
      ];
    }

    $teller = DB::table('event_tellers_view')
      ->select(array_merge(self::TELLER_COLUMNS, ['teller_match_balance']))
      ->where('event_teller_id', $eventTellerId)
      ->first();

    return response()->json([
      'teller' => $teller,
      'total_bet' => $this->tellerTotalBet((int) $eventTellerId),
      'total_payout' => (float) Claim::where('event_teller_id', $eventTellerId)->sum('claim_amount'),
      'cash_on_hand' => (float) ($teller->teller_balance ?? 0),
      'fights' => $fights,
    ]);
  }

  public function eventTotals(Request $request)
  {
    $event = $this->eventOrActive($request->query('event_id'));
    if (!$event) {
      return response()->json(['message' => 'Event not found'], 404);
    }

    $totalCollections = DB::table('bets_view')
      ->where('event_id', $event->event_id)
      ->where('bet_status', 1)
      ->where('match_status', 'Completed')
      ->sum('bet_amount');

    $cashIn = DB::table('cash_ins_view')
      ->where('event_id', $event->event_id)
      ->where('cash_in_status', 'Approved')
      ->sum('cash_in_amount');

    $cashOut = DB::table('cash_outs_view')
      ->where('event_id', $event->event_id)
      ->where('cash_out_status', 'Approved')
      ->sum('cash_out_amount');

    return response()->json([
      'total_collections' => (float) $totalCollections,
      'total_fights' => (int) DB::table('matches')->where('event_id', $event->event_id)->count(),
      'cash_on_hand' => (float) (($event->admin_cash_on_hand - $cashIn) + $cashOut),
    ]);
  }

  public function cashTransactions(Request $request)
  {
    $event = $this->eventOrActive($request->query('event_id'));
    if (!$event) {
      return response()->json(['message' => 'Event not found'], 404);
    }

    $tellers = DB::table('event_tellers_view')
      ->select(self::TELLER_COLUMNS)
      ->where('event_id', $event->event_id)
      ->orderBy('teller_name', 'asc')
      ->get();

    return response()->json([
      'event' => $event,
      'tellers' => $this->withCashSummaries($tellers),
    ]);
  }

  public function tellerCashSummary($eventTellerId)
  {
    $teller = DB::table('event_tellers_view')
      ->select(self::TELLER_COLUMNS)
      ->where('event_teller_id', $eventTellerId)
      ->first();

    if (!$teller) {
      return response()->json(['message' => 'Teller not found'], 404);
    }

    return response()->json(array_merge([
      'event' => Event::where('event_id', $teller->event_id)->first(),
      'teller' => $teller,
    ], $this->buildTellerCashSummary((int) $eventTellerId, $teller)));
  }

  /**
   * Full analytics snapshot payload for the print service (read-only).
   */
  public function analyticsEvent($eventId)
  {
    $event = Event::where('event_id', $eventId)->first();
    if (!$event) {
      return response()->json(['message' => 'Event not found'], 404);
    }

    $gross = (float) DB::table('bets_view')
      ->where('event_id', $event->event_id)
      ->where('bet_status', 1)
      ->whereIn('bet_side', ['Meron', 'Wala'])
      ->where('match_status', 'Completed')
      ->sum('bet_amount');

    $payout = (float) DB::table('claims')
      ->join('event_tellers', 'claims.event_teller_id', '=', 'event_tellers.event_teller_id')
      ->where('event_tellers.event_id', $event->event_id)
      ->sum('claims.claim_amount');

    $unclaim = (float) DB::table('bets_view')
      ->where('event_id', $event->event_id)
      ->whereNull('bet_payout_datetime')
      ->where('bet_payout_amount', '>', 0)
      ->where('bet_status', 1)
      ->sum('bet_payout_amount');

    $tellers = DB::table('event_tellers_view')
      ->select(self::TELLER_COLUMNS)
      ->where('event_id', $event->event_id)
      ->orderBy('teller_name', 'asc')
      ->get();

    $tellerRows = [];
    foreach ($tellers as $tel) {
      $tellerRows[] = array_merge([
        'event_teller_id' => (int) $tel->event_teller_id,
        'teller_id' => (int) $tel->teller_id,
        'teller_name' => $tel->teller_name,
      ], $this->buildTellerCashSummary((int) $tel->event_teller_id, $tel));
    }

    usort($tellerRows, fn ($a, $b) => $b['total_bet'] <=> $a['total_bet']);

    return response()->json([
      'event' => [
        'event_id' => (int) $event->event_id,
        'event_name' => $event->event_name,
        'event_date' => $event->event_date,
        'event_status' => $event->event_status,
      ],
      'gross' => $gross,
      'payout' => $payout,
      'unclaim' => $unclaim,
      'fight_count' => (int) DB::table('matches')->where('event_id', $event->event_id)->count(),
      'tellers' => $tellerRows,
    ]);
  }

  private function eventOrActive($eventId): ?Event
  {
    return $eventId
      ? Event::where('event_id', $eventId)->first()
      : Event::where('event_status', 'Active')->first();
  }

  private function withCashSummaries($tellers)
  {
    foreach ($tellers as $tel) {
      foreach ($this->buildTellerCashSummary((int) $tel->event_teller_id, $tel) as $key => $value) {
        $tel->$key = $value;
      }
    }
    return $tellers;
  }

  private function tellerTotalBet(int $eventTellerId): float
  {
    return (float) DB::table('bets_view')
      ->where('event_teller_id', $eventTellerId)
      ->where('bet_status', 1)
      ->whereNotIn('match_status', ['Draw', 'Cancelled'])
      ->sum('bet_amount');
  }

  private function buildTellerCashSummary(int $eventTellerId, $teller): array
  {
    $totalCashIn = DB::table('cash_ins_view')
      ->where('event_teller_id', $eventTellerId)
      ->where('cash_in_status', 'Approved')
      ->sum('cash_in_amount');

    $totalCashOut = DB::table('cash_outs_view')
      ->where('event_teller_id', $eventTellerId)
      ->where('cash_out_status', 'Approved')
      ->sum('cash_out_amount');

    return [
      'total_bet' => $this->tellerTotalBet($eventTellerId),
      'total_payout' => (float) Claim::where('event_teller_id', $eventTellerId)->sum('claim_amount'),
      'total_cash_in' => (float) $totalCashIn,
      'total_cash_out' => (float) $totalCashOut,
      'cash_on_hand' => (float) ($teller->teller_balance ?? 0),
    ];
  }
}
