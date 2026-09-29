<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Claim;
use App\Models\Event;
use App\Models\CashIn;
use App\Models\Teller;
use App\Models\Account;
use App\Models\EventTeller;
use Illuminate\Http\Request;
use App\Events\CashInUpdated;
use App\Events\CashOutUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;

class EventTellerController extends Controller
{

  public function getData(Request $r)
  {

    $account = DB::table('event_tellers_view')
    ->where('event_teller_id', $r->event_teller_id)
    ->where('event_status', 'Active')
    ->first();

    if (empty($account)) {
        return response()->json([
            'token' => null,
            'teller' => null,
            'event' => null,
            'match' => null,
            'error' => 'No active event found for the given teller. Please relogin app.'
        ], 404);
    }

    $latestMatch = DB::table('matches')
    ->where('event_id', $account->event_id)
    ->orderByDesc('match_number')
    ->first();



    $res = response()->json([
      'token' => '',
      'teller' => [
          'phone_uid' => $account->phone_uid,
          'event_teller_id' => $account->event_teller_id,
          'teller_id' => $account->teller_id,
          'teller_name' => $account->teller_name,
          'teller_balance' => $account->teller_balance,
          'teller_match_balance' => $account->teller_match_balance,
      ],
      'event' => [
          'event_id' => $account->event_id,
          'event_name' => $account->event_name,
          'event_status' => $account->event_status
      ],
      'match' => $latestMatch ? [
          'match_id' => $latestMatch->match_id,
          'match_number' => $latestMatch->match_number,
          'match_bet_status' => $latestMatch->match_bet_status
      ] : null
  ], 200);


  return $res;
  }

  public function getTellers(Request $r)
  {

    $event_tellers = DB::table('event_tellers_view')
    ->where('event_id', $r->event_id)
    ->orderBy('teller_name', 'asc')
    ->get();

    foreach($event_tellers as &$tel)
    {
      $total_bet = DB::table('bets_view')->where('event_teller_id', $tel->event_teller_id)
        ->where('bet_status', 1)
        ->where(function ($query) {
          $query->where('match_status', '<>', 'Draw')
                ->Where('match_status', '<>', 'Cancelled');
      })

      ->sum('bet_amount');

      $total_payout = Claim::where('event_teller_id', $tel->event_teller_id)
      ->sum('claim_amount');


      $tel->total_payout = $total_payout;

      $tel->total_bet = $total_bet;
    }

    broadcast(new TellerBalanceUpdated($event_tellers));

    $cash_ins = DB::table('cash_ins_view')
    ->where('event_id', $r->event_id)
    ->orderBy('cash_in_id', 'desc')
    ->get();

    broadcast(new CashInUpdated($cash_ins));

    $cash_outs = DB::table('cash_outs_view')
    ->where('event_id', $r->event_id)
    ->orderBy('cash_out_id', 'desc')
    ->get();


    broadcast(new CashOutUpdated($cash_outs));
  }



  public function listByEvent($eventId)
  {
      $tellers = Teller::get();
      $assignedTellers = EventTeller::where('event_id', $eventId)
        ->pluck('teller_id')
        ->map(fn ($id) => (int) $id)
        ->values()
        ->toArray();

      return response()->json([
          'tellers' => $tellers,
          'assigned_tellers' => $assignedTellers
      ]);
  }

  public function listByEventApp(Request $r)
  {
      $tellers = DB::table('tellers')
        ->leftJoin('accounts', 'accounts.account_id', '=', 'tellers.account_id')
        ->orderBy('tellers.teller_name')
        ->get([
            'tellers.teller_id',
            'tellers.teller_name',
            'accounts.is_active',
        ]);

      $assignedTellers = EventTeller::where('event_id', $r->event_id)
        ->pluck('teller_id')
        ->map(fn ($id) => (int) $id)
        ->values();

      return response()->json([
          'tellers' => $tellers,
          'assigned_tellers' => $assignedTellers,
      ]);
  }


  public function listTellersAdmin()
  {
      $event = Event::where('event_status', 'Active')->first();
      if (empty($event)) {
          return response()->json([
              'success' => false,
              'tellers' => [],
          ]);
      }

      $event_tellers = DB::table('event_tellers_view')
          ->orderBy('teller_name', 'asc')
          ->where('event_id', $event->event_id)
          ->get();

      foreach ($event_tellers as $tel) {
          $this->attachTellerAckTotals($tel);
      }

      return response()->json([
          'success' => true,
          'tellers' => $event_tellers,
      ]);
  }

  private function attachTellerAckTotals($tel)
  {
      $tel->total_bet = DB::table('bets_view')
          ->where('event_teller_id', $tel->event_teller_id)
          ->where('bet_status', 1)
          ->where(function ($query) {
              $query->where('match_status', '<>', 'Draw')
                    ->where('match_status', '<>', 'Cancelled');
          })
          ->sum('bet_amount');

      $tel->total_payout = Claim::where('event_teller_id', $tel->event_teller_id)
          ->sum('claim_amount');

      $tel->total_cash_in = DB::table('cash_ins_view')
          ->where('event_teller_id', $tel->event_teller_id)
          ->where('cash_in_status', 'Approved')
          ->sum('cash_in_amount');

      $tel->total_cash_out = DB::table('cash_outs_view')
          ->where('event_teller_id', $tel->event_teller_id)
          ->where('cash_out_status', 'Approved')
          ->sum('cash_out_amount');
  }

  public function listTellersByEvent(Request $r)
  {
      return DB::table('event_tellers_view')
      ->orderBy('teller_name', 'asc')
      ->where('event_id', $r->event_id)
      ->get();
  }

  public function updateTellers(Request $request, $eventId)
  {
      $event = Event::where('event_id', $eventId)->first();
      $admin = session()->get('admin');
      $adminId = $request->admin_id ?? (is_object($admin) ? $admin->admin_id : null);

      if (!$event) {
          return response()->json(['error' => 'Event not found'], 404);
      }
      if (!$adminId) {
          return response()->json(['error' => 'Missing admin id'], 400);
      }

      DB::beginTransaction();
      try {

          $tellersWithBets = DB::table('bets_view')
              ->where('event_id', $eventId)
              ->distinct()
              ->pluck('teller_id')
              ->toArray();


          // Tellers leaving the event (only possible if they have no bets): close their
          // ledger (opening cash returned), then remove them.
          $removed = EventTeller::where('event_id', $eventId)
              ->whereNotIn('teller_id', $tellersWithBets)
              ->whereNotIn('teller_id', $request->tellers ?? [])
              ->get();
          foreach ($removed as $gone) {
              $bal = (float) $gone->teller_balance;
              DB::table('event_tellers')->where('event_teller_id', $gone->event_teller_id)->update(['teller_balance' => 0]);
              if (DB::table('teller_ledger')->where('event_teller_id', $gone->event_teller_id)->exists() || $bal != 0) {
                  \App\Services\Ledger\TellerLedger::record((int) $gone->event_teller_id, 'adjustment', 0, $bal, [
                      'note' => 'Teller removed from the event (no bets); cash on hand returned',
                      'source_table' => 'event_tellers', 'source_id' => (int) $gone->event_teller_id,
                  ]);
              }
          }
          $removedIds = $removed->pluck('event_teller_id')->all();
          EventTeller::whereIn('event_teller_id', $removedIds ?: [0])->delete();


          if (!empty($request->tellers)) {
              foreach ($request->tellers as $tellerId) {

                  $exists = EventTeller::where('event_id', $eventId)
                      ->where('teller_id', $tellerId)
                      ->exists();

                  if (!$exists) {
                      $event_teller = EventTeller::create([
                          'event_id' => $eventId,
                          'teller_id' => $tellerId,
                          'teller_balance' => $event->teller_initial_cash_on_hand,
                          'teller_match_balance' => 0,
                          'is_disabled' => 0
                      ]);

                      $opening = CashIn::create([
                        'admin_id' => $adminId,
                        'event_id' => $eventId,
                        'event_teller_id' => $event_teller->event_teller_id,
                        'cash_in_amount' => $event->teller_initial_cash_on_hand,
                        'cash_in_datetime' => date('Y-m-d H:i:s'),
                        'cash_in_type' => 'AdminCashInInit',
                        'cash_in_status' => 'Approved',
                      ]);
                      \App\Services\Ledger\TellerLedger::cashDecision('in', $opening->fresh(), 'Approved');

                      // $teller = Teller::where('teller_id', $tellerId)
                      // ->first();

                      // $teller->phone_uid = '';
                      // $teller->save();
                  }
              }
          }

          // Get remaining event_teller_ids after update
        $remainingTellerIds = EventTeller::where('event_id', $eventId)
            ->pluck('event_teller_id')
            ->toArray();

        // Delete the opening cash-ins of tellers removed from THIS event only.
        // (This used to be whereNotIn(remaining ids), which deleted the cash-ins
        // of every other event's tellers as well.)
        CashIn::whereIn('event_teller_id', $removedIds ?: [0])
            ->delete();




          $cash_ins = DB::table('cash_ins_view')
          ->where('event_id', $eventId)
          ->orderBy('cash_in_id', 'desc')
          ->get();

          broadcast(new CashInUpdated($cash_ins));

          $this->createLog(session()->get('account_id'), "Web App", "Event - Event tellers updated. " . implode(',', $request->tellers ?? []));


          DB::commit();
          return response()->json(['message' => 'Tellers updated successfully!']);
      } catch (\Exception $e) {

          DB::rollBack();
          return response()->json(['error' => 'Failed to update tellers: ' . $e->getMessage()], 500);
      }
  }

  public function updateTellersApp(Request $r)
  {
      return $this->updateTellers($r, $r->event_id);
  }

}
