<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Fight;
use App\Models\Teller;
use App\Models\EventTeller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;

class ClaimController extends Controller
{
    public function claimWinning(Request $r)
    {
      $time_now = date('Y-m-d H:i:s');
      if (!is_scalar($r->receipt_code) || trim((string) $r->receipt_code) === '') {
        return response()->json(['success' => false, 'message' => 'Receipt code is required.'], 422);
      }
      $fail = function (string $message) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => $message], 200);
      };

      DB::beginTransaction();
      try {

        // Lock the bet row: the same receipt presented at two tellers at the
        // same moment is paid exactly once (the unique index on claims.bet_id
        // is the second line of defence).
        $bet = Bet::where('bet_receipt_code', (string) $r->receipt_code)->lockForUpdate()->first();

        if (empty($bet)) {
          return $fail('Receipt not found. Please check the receipt code.');
        }

        if($bet->bet_status == 2)
        {
          return $fail('Payout cannot be processed for this receipt because it has been voided.');
        }

        if($bet->bet_payout_amount <= 0)
        {
          return $fail('This receipt cannot be processed for payout. Please contact the administrator for assistance.');
        }

        $event_teller = DB::table('event_tellers_view')->where('event_teller_id', $bet->event_teller_id)->first();

        if(!$event_teller || $event_teller->event_status <> 'Active')
        {
          return $fail('This receipt is not eligible for payout as the event is no longer active.');
        }

        $is_claimed = DB::table('claims_view')->where('bet_id', $bet->bet_id)
        ->first();

        if(!empty($is_claimed))
        {
          return $fail('This receipt was already claimed by ' . $is_claimed->teller_name . ' on ' . date('F j, Y h:i a', strtotime($is_claimed->claim_datetime)));
        }

        // Atomic: deduct only if the paying teller has enough cash on hand.
        $deducted = EventTeller::where('event_teller_id', $r->event_teller_id)
          ->where('teller_balance', '>=', $bet->bet_payout_amount)
          ->update(['teller_balance' => DB::raw('teller_balance - ' . (float) $bet->bet_payout_amount)]);
        if ($deducted !== 1) {
          return $fail('Insufficient cash on hand. Please request a cash-in from the administrator.');
        }

         $claim = Claim::create([
            'bet_id' => $bet->bet_id,
            'event_teller_id' => $r->event_teller_id,
            'claim_amount' => $bet->bet_payout_amount,
            'claim_datetime' => $time_now,
        ]);

        $bet->bet_payout_datetime = $time_now;
        $bet->bet_payout_by = $r->event_teller_id;
        $bet->save();

        $receiptFight = DB::table('matches')->where('match_id', $bet->match_id)->first(['match_number', 'match_status']);
        $crossTeller = (int) $bet->event_teller_id !== (int) $r->event_teller_id;
        \App\Services\Ledger\TellerLedger::record((int) $r->event_teller_id, $bet->bet_is_winner == 1 ? 'payout_win' : 'payout_refund', 0, $bet->bet_payout_amount, [
          'fight_no' => $receiptFight ? (int) $receiptFight->match_number : null, 'match_id' => (int) $bet->match_id,
          'reference' => $bet->bet_receipt_code, 'related_event_teller_id' => (int) $bet->event_teller_id,
          'note' => ($bet->bet_is_winner == 1 ? $bet->bet_side . ' win' : ($receiptFight->match_status ?? 'Refund') . ' refund')
            . ($crossTeller ? '; receipt issued by another teller' : ''),
          'source_table' => 'claims', 'source_id' => (int) $claim->claim_id,
        ]);

        $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();





          DB::commit();


          $event_tellers = DB::table('event_tellers_view')
              ->where('event_id', $event_teller->event_id)
              ->orderBy('teller_name')
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

          $match = Fight::where('match_id', $bet->match_id)
          ->first();

          $teller = Teller::where('teller_id', $event_teller->teller_id)->first();
          $this->createLog($teller->account_id, "Teller App", "Bet payout claimed. " . json_encode($bet) . " " .  json_encode($teller));

          $claim->teller_name = $teller->teller_name;
          $event = Event::where('event_id', $match->event_id)->first();
          $match->event_name = $event->event_name;

          $res = response()->json([
              'success' => true,
              'bet' => $bet,
              'match' => $match,
              'claim' => $claim,
              'message' => 'Payout has been created successfully.'
          ], 200);


          return $res;


      } catch (\Exception $e) {

          DB::rollBack();


          return response()->json([
              'success' => false,
              'message' => 'Something went wrong! Please try again!',
              'error'   => $e->getMessage()
          ], 500);
      }
  }

  public function claimWinningReprint(Request $r)
    {

      DB::beginTransaction();
      try {

        $bet = DB::table('bets_view')->where('bet_receipt_code', $r->receipt_code)
        ->first();

        $match = Fight::where('match_id', $bet->match_id)
        ->first();

        $match->event_name = $bet->event_name;

        $claim = DB::table('claims_view')
        ->where('bet_id', $bet->bet_id)
        ->first();



          $res = response()->json([
              'success' => true,
              'bet' => $bet,
              'match' => $match,
              'claim' => $claim,
              'message' => 'Reprint payout successfully.'
          ], 200);


          return $res;


      } catch (\Exception $e) {

          DB::rollBack();


          return response()->json([
              'success' => false,
              'message' => 'Something went wrong! Please try again!',
              'error'   => $e->getMessage()
          ], 500);
      }
  }

  public function cashOutHistory(Request $r)
  {

    try {

      $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

      $event = Event::where('event_id', $event_teller->event_id)->first();

      if($event->event_status <> 'Active')
      {

        return response()->json([
            'success' => false,
            'message' => 'Event is no longer Active.'
        ], 200);
      }


      $history = DB::table('claims_view')
          ->where('event_teller_id', $r->event_teller_id)
          ->orderBy('claim_datetime', 'desc')
          ->get();

      return response()->json([
          'success' => true,
          'message' => 'Payout history loaded successfully.',
          'data'    => $history
      ], 200);

  } catch (\Exception $e) {

      return response()->json([
          'success' => false,
          'message' => 'Unable to retrieve payout history.',
          'error'   => $e->getMessage()
      ], 500);
  }
  }
}
