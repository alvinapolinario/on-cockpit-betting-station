<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Admin;
use App\Models\Claim;
use App\Models\Event;
use App\Models\CashIn;
use App\Models\Teller;
use App\Models\EventTeller;
use Illuminate\Http\Request;
use App\Events\CashInUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;

class CashInController extends Controller
{
  public function storeByAdmin(Request $r)
  {
    if (!is_numeric($r->cash_in_amount) || $r->cash_in_amount <= 0 || $r->cash_in_amount > config('betting.max_cash_movement')) {
      return response()->json(['success' => false, 'message' => 'Enter an amount between 1 and ' . number_format(config('betting.max_cash_movement')) . '.'], 422);
    }

    DB::beginTransaction();


    try {
       $cash_in = CashIn::create([
        'admin_id' => session()->get('admin')->admin_id,
        'event_teller_id' => $r->event_teller_id,
        'cash_in_amount' => $r->cash_in_amount,
        'cash_in_datetime' => date('Y-m-d H:i:s'),
        'cash_in_type' => 'AdminCashIn',
        'cash_in_status' => 'Approved',
      ]);

      $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

      EventTeller::where('event_teller_id', $event_teller->event_teller_id)->update(['teller_balance' => DB::raw('teller_balance + ' . (float) $r->cash_in_amount)]);
      \App\Services\Ledger\TellerLedger::cashDecision('in', $cash_in, 'Approved');

      $this->createLog(session()->get('account_id'), "Web App", "Cash Transactions - Cash-in created. " . $cash_in . " " . $event_teller);


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

      $cash_ins = DB::table('cash_ins_view')
      ->where('event_id', $event_teller->event_id)
      ->orderBy('cash_in_id', 'desc')
      ->get();

      broadcast(new CashInUpdated($cash_ins));

      return 1;

    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }

  public function storeByTeller(Request $r)
{
    if (!is_numeric($r->amount) || $r->amount <= 0 || $r->amount > config('betting.max_cash_movement')) {
      return response()->json(['success' => false, 'message' => 'Enter an amount between 1 and ' . number_format(config('betting.max_cash_movement')) . '.'], 422);
    }

    DB::beginTransaction();
    try {

      $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

      $event = Event::where('event_id', $event_teller->event_id)->first();

      if($event->event_status <> 'Active')
      {

        return response()->json([
            'success' => false,
            'message' => 'Cash-in request failed, event is no longer Active.'
        ], 200);
      }


       $cash_in = CashIn::create([
            'admin_id' => null,
            'event_teller_id' => $r->event_teller_id,
            'cash_in_amount' => $r->amount,
            'cash_in_datetime' => date('Y-m-d H:i:s'),
            'cash_in_type' => 'TellerCashIn',
            'cash_in_status' => 'Pending',
        ]);


        $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

        $teller = Teller::where('teller_id', $event_teller->teller_id)->first();

        $this->createLog($teller->account_id, "Teller App", "Cash-in request by Teller. " . json_encode($cash_in) . " " .  json_encode($teller));


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


        $cash_ins = DB::table('cash_ins_view')
            ->where('event_id', $event_teller->event_id)
            ->orderBy('cash_in_id', 'desc')
            ->get();
        broadcast(new CashInUpdated($cash_ins));


        return response()->json([
            'success' => true,
            'message' => 'Cash-in request submitted successfully.'
        ], 200);

    } catch (\Exception $e) {

        DB::rollBack();


        return response()->json([
            'success' => false,
            'message' => 'Something went wrong! Please try again!',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function storeByAdminViaApp(Request $r)
{
    if (!is_numeric($r->amount) || $r->amount <= 0 || $r->amount > config('betting.max_cash_movement')) {
      return response()->json(['success' => false, 'message' => 'Enter an amount between 1 and ' . number_format(config('betting.max_cash_movement')) . '.'], 422);
    }

    DB::beginTransaction();
    try {

       $cash_in = CashIn::create([
            'admin_id' => $r->admin_id,
            'event_teller_id' => $r->event_teller_id,
            'cash_in_amount' => $r->amount,
            'cash_in_datetime' => date('Y-m-d H:i:s'),
            'cash_in_type' => 'AdminCashIn',
            'cash_in_status' => 'Approved',
        ]);


        $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

        EventTeller::where('event_teller_id', $event_teller->event_teller_id)->update(['teller_balance' => DB::raw('teller_balance + ' . (float) $cash_in->cash_in_amount)]);
        \App\Services\Ledger\TellerLedger::cashDecision('in', $cash_in, 'Approved');


        $admin = Admin::where('admin_id', $r->admin_id)->first();

        $this->createLog($admin->account_id, "Admin App", "Cash-in by Admin. " . json_encode($cash_in) . " " .  json_encode($admin) . " " .  json_encode($event_teller));



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


        $cash_ins = DB::table('cash_ins_view')
            ->where('event_id', $event_teller->event_id)
            ->orderBy('cash_in_id', 'desc')
            ->get();
        broadcast(new CashInUpdated($cash_ins));

        $cash_in = DB::Table('cash_ins_view')
        ->where('cash_in_id', $cash_in->cash_in_id)
        ->first();

        return response()->json([
            'success' => true,
            'cash_in' => $cash_in,
            'message' => 'Cash-in was successful!'
        ], 200);

    } catch (\Exception $e) {

        DB::rollBack();


        return response()->json([
            'success' => false,
            'message' => 'Something went wrong! Please try again!',
            'error'   => $e->getMessage()
        ], 500);
    }
}


  public function approveCashIn(Request $request)
  {
      $cashInId = $request->input('cash_in_id');

      $cashIn = CashIn::find($cashInId);
      if (!$cashIn || $cashIn->cash_in_status !== 'Pending') {
          return response()->json(['error' => 'Invalid request'], 400);
      }

      DB::beginTransaction();
      try {
          // Atomic: only one request can take this out of Pending (no double approval),
          // and the teller balance changes in the same transaction.
          $newStatus = 'Approved';
          $won = CashIn::where('cash_in_id', $cashIn->cash_in_id)->where('cash_in_status', 'Pending')
            ->update(['cash_in_status' => $newStatus, 'admin_id' => session()->get('admin')->admin_id]);
          if ($won !== 1) {
            DB::rollBack();
            return response()->json(['error' => 'This request was already processed.'], 409);
          }
          if ($newStatus === 'Approved') {
            EventTeller::where('event_teller_id', $cashIn->event_teller_id)
              ->update(['teller_balance' => DB::raw('teller_balance + ' . (float) $cashIn->cash_in_amount)]);
          }
          $cashIn->refresh();
          \App\Services\Ledger\TellerLedger::cashDecision('in', $cashIn, $newStatus);

          DB::commit();

          $cash_in = DB::table('cash_ins_view')
          ->where('cash_in_id', $cashInId)
          ->first();


          $cash_ins = DB::table('cash_ins_view')
          ->where('event_id', $cash_in->event_id)
          ->orderBy('cash_in_id', 'desc')
          ->get();

          $event_teller = EventTeller::where('event_teller_id', $cashIn->event_teller_id)
          ->first();

          $this->createLog(session()->get('account_id'), "Web App", "Cash Transactions - Cash-in approved. " . $cashIn . " " . $event_teller);


          $event_tellers = DB::table('event_tellers_view')
          ->where('event_id', $cash_in->event_id)
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

          broadcast(new CashInUpdated($cash_ins));

          return response()->json(['success' => 'The cash-in request has been approved.']);
      } catch (\Exception $e) {
          DB::rollBack();

          return response()->json(['error' => 'Approval failed. Please try again.'], 500);
      }
  }

  public function approvalViaApp(Request $request)
  {
      $cashInId = $request->cash_in_id;
      if (!in_array($request->cash_in_status, ['Approved', 'Declined'], true)) {
          return response()->json(['success' => false, 'message' => 'Status must be Approved or Declined.'], 422);
      }

      $cashIn = CashIn::find($cashInId);
      if (!$cashIn || $cashIn->cash_in_status !== 'Pending') {
          return response()->json([
            'success' => false,
            'message' => 'Invalid request']
            , 200);
      }

      DB::beginTransaction();
      try {
          // Atomic: only one request can take this out of Pending (no double approval),
          // and the teller balance changes in the same transaction.
          $newStatus = $request->cash_in_status;
          $won = CashIn::where('cash_in_id', $cashIn->cash_in_id)->where('cash_in_status', 'Pending')
            ->update(['cash_in_status' => $newStatus, 'admin_id' => $request->admin_id]);
          if ($won !== 1) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'This request was already processed.'], 200);
          }
          if ($newStatus === 'Approved') {
            EventTeller::where('event_teller_id', $cashIn->event_teller_id)
              ->update(['teller_balance' => DB::raw('teller_balance + ' . (float) $cashIn->cash_in_amount)]);
          }
          $cashIn->refresh();
          \App\Services\Ledger\TellerLedger::cashDecision('in', $cashIn, $newStatus);

          $admin = Admin::where('admin_id', $request->admin_id)->first();

          $this->createLog($admin->account_id, "Admin App", "Cash-in approval by Admin. " . json_encode($cashIn) . " " .  json_encode($admin));


          DB::commit();

          $cash_in = DB::table('cash_ins_view')
          ->where('cash_in_id', $cashInId)
          ->first();

          $cash_ins = DB::table('cash_ins_view')
          ->where('event_id', $cash_in->event_id)
          ->orderBy('cash_in_id', 'desc')
          ->get();

          $event_teller = EventTeller::where('event_teller_id', $cash_in->event_teller_id)
          ->first();

          if($request->cash_in_status == 'Approved')
          {
          }



          $event_tellers = DB::table('event_tellers_view')
          ->where('event_id', $cash_in->event_id)
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

          broadcast(new CashInUpdated($cash_ins));

          return response()->json([
            'success' => true,
            'message' => 'Cash-in has been approved successfully!'
          ],200);


      } catch (\Exception $e) {
          DB::rollBack();

          return response()->json([
            'success' => false,
            'message' => 'Approval failed. Please try again.'
          ], 500);
      }
  }

  public function declineCashIn(Request $request)
  {
      $cashInId = $request->input('cash_in_id');

      $cashIn = CashIn::find($cashInId);
      if (!$cashIn || $cashIn->cash_in_status !== 'Pending') {
          return response()->json(['error' => 'Invalid request'], 400);
      }

      DB::beginTransaction();
      try {
          // Atomic: only one request can take this out of Pending (no double approval),
          // and the teller balance changes in the same transaction.
          $newStatus = 'Declined';
          $won = CashIn::where('cash_in_id', $cashIn->cash_in_id)->where('cash_in_status', 'Pending')
            ->update(['cash_in_status' => $newStatus, 'admin_id' => session()->get('admin')->admin_id]);
          if ($won !== 1) {
            DB::rollBack();
            return response()->json(['error' => 'This request was already processed.'], 409);
          }
          if ($newStatus === 'Approved') {
            EventTeller::where('event_teller_id', $cashIn->event_teller_id)
              ->update(['teller_balance' => DB::raw('teller_balance + ' . (float) $cashIn->cash_in_amount)]);
          }
          $cashIn->refresh();
          \App\Services\Ledger\TellerLedger::cashDecision('in', $cashIn, $newStatus);

          DB::commit();

          $cash_in = DB::table('cash_ins_view')
          ->where('cash_in_id', $cashInId)
          ->first();

          $cash_ins = DB::table('cash_ins_view')
          ->where('event_id', $cash_in->event_id)
          ->orderBy('cash_in_id', 'desc')
          ->get();

          $event_teller = EventTeller::where('event_teller_id', $cash_in->event_teller_id)
          ->first();

          $this->createLog(session()->get('account_id'), "Web App", "Cash Transactions - Cash-in declined. " . $cashIn . " " . $event_teller);


          $event_tellers = DB::table('event_tellers_view')
          ->where('event_id', $cash_in->event_id)
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

          broadcast(new CashInUpdated($cash_ins));

          return response()->json(['success' => 'Cash-in approved successfully.']);
      } catch (\Exception $e) {
          DB::rollBack();

          return response()->json(['error' => 'Approval failed. Please try again.'], 500);
      }
  }

  public function cashInHistory(Request $r)
  {
      try {

          $history = DB::table('cash_ins_view')
              ->where('event_teller_id', $r->event_teller_id)
              ->orderBy('cash_in_datetime', 'desc')
              ->get();


          return response()->json([
              'success' => true,
              'message' => 'Cash-in history loaded successfully.',
              'data'    => $history
          ], 200);

      } catch (\Exception $e) {

          return response()->json([
              'success' => false,
              'message' => 'Unable to retrieve cash-in history.',
              'error'   => $e->getMessage()
          ], 500);
      }
  }

  public function cashInHistoryAdmin(Request $r)
  {
      try {

        $event = Event::where('event_status', 'Active')->first();

          $history = DB::table('cash_ins_view')
              ->where('event_id', $event->event_id)
              ->orderBy('cash_in_datetime', 'desc')
              ->get();


          return response()->json([
              'success' => true,
              'message' => 'Cash-in history loaded successfully.',
              'data'    => $history
          ], 200);

      } catch (\Exception $e) {

          return response()->json([
              'success' => false,
              'message' => 'Unable to retrieve cash-in history.',
              'error'   => $e->getMessage()
          ], 500);
      }
  }

}
