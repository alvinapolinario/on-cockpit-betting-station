<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Admin;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Teller;
use App\Models\CashOut;
use App\Models\EventTeller;
use Illuminate\Http\Request;
use App\Events\CashOutUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;

class CashOutController extends Controller
{
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


        if($r->amount > $event_teller->teller_balance)
        {
          return response()->json([
              'success' => false,
              'message' => "Not enough cash on hand."
          ], 200);
        }

        $cash_out = CashOut::create([
            'admin_id' => null,
            'event_teller_id' => $r->event_teller_id,
            'cash_out_amount' => $r->amount,
            'cash_out_datetime' => date('Y-m-d H:i:s'),
            'cash_out_status' => 'Pending',
        ]);

        $teller = Teller::where('teller_id', $event_teller->teller_id)->first();


        $this->createLog($teller->account_id, "Teller App", "Cash-out request by Teller. " . json_encode($cash_out) . " " .  json_encode($teller));




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


        $cash_outs = DB::table('cash_outs_view')
            ->where('event_id', $event_teller->event_id)
            ->orderBy('cash_out_id', 'desc')
            ->get();
        broadcast(new CashOutUpdated($cash_outs));


        return response()->json([
            'success' => true,
            'message' => 'Cash-out request submitted successfully.'
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

      $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

      if($r->amount > $event_teller->teller_balance)
      {
        return response()->json([
            'success' => false,
            'message' => "Not enough cash on hand."
        ], 200);
      }

       $cash_out = CashOut::create([
            'admin_id' => $r->admin_id,
            'event_teller_id' => $r->event_teller_id,
            'cash_out_amount' => $r->amount,
            'cash_out_datetime' => date('Y-m-d H:i:s'),
            'cash_out_status' => 'Approved',
        ]);

        $ok = EventTeller::where('event_teller_id', $event_teller->event_teller_id)->where('teller_balance', '>=', $cash_out->cash_out_amount)
          ->update(['teller_balance' => DB::raw('teller_balance - ' . (float) $cash_out->cash_out_amount)]);
        if ($ok !== 1) {
          DB::rollBack();
          return response()->json(['success' => false, 'message' => 'Not enough cash on hand.'], 200);
        }
        \App\Services\Ledger\TellerLedger::cashDecision('out', $cash_out, 'Approved');


        $admin = Admin::where('admin_id', $r->admin_id)->first();

        $this->createLog($admin->account_id, "Admin App", "Cash-out by Admin. " . json_encode($cash_out) . " " .  json_encode($admin) . " " .  json_encode($event_teller));

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


        $cash_outs = DB::table('cash_outs_view')
            ->where('event_id', $event_teller->event_id)
            ->orderBy('cash_out_id', 'desc')
            ->get();
        broadcast(new CashOutUpdated($cash_outs));

        $cash_out = DB::table('cash_outs_view')
        ->where('cash_out_id', $cash_out->cash_out_id)
        ->first();

        return response()->json([
          'cash_out' => $cash_out,
            'success' => true,
            'message' => 'Cash-out was successful!'
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

  public function approveCashOut(Request $request)
  {

      $cashOutId = $request->input('cash_out_id');

      $cashOut = CashOut::find($cashOutId);
      if (!$cashOut || $cashOut->cash_out_status !== 'Pending') {
          return response()->json(['error' => 'Invalid request'], 400);
      }

      DB::beginTransaction();
      try {
          // Atomic: only one request can take this out of Pending (no double approval),
          // and the teller balance changes in the same transaction.
          $newStatus = 'Approved';
          $won = CashOut::where('cash_out_id', $cashOut->cash_out_id)->where('cash_out_status', 'Pending')
            ->update(['cash_out_status' => $newStatus, 'admin_id' => session()->get('admin')->admin_id]);
          if ($won !== 1) {
            DB::rollBack();
            return response()->json(['error' => 'This request was already processed.'], 409);
          }
          if ($newStatus === 'Approved') {
            $ok = EventTeller::where('event_teller_id', $cashOut->event_teller_id)
              ->where('teller_balance', '>=', $cashOut->cash_out_amount)
              ->update(['teller_balance' => DB::raw('teller_balance - ' . (float) $cashOut->cash_out_amount)]);
            if ($ok !== 1) {
              DB::rollBack();
              return response()->json(['error' => 'Teller does not have enough cash on hand for this cash-out.'], 409);
            }
          }
          $cashOut->refresh();
          \App\Services\Ledger\TellerLedger::cashDecision('out', $cashOut, $newStatus);

          DB::commit();

          $cash_out = DB::table('cash_outs_view')
          ->where('cash_out_id', $cashOutId)
          ->first();

          $cash_outs = DB::table('cash_outs_view')
          ->where('event_id', $cash_out->event_id)
          ->orderBy('cash_out_id', 'desc')
          ->get();

          $event_teller = EventTeller::where('event_teller_id', $cash_out->event_teller_id)
          ->first();

          $this->createLog(session()->get('account_id'), "Web App", "Cash Transactions - Cash-out approved. " . $cashOut . " " . $event_teller);

          $event_tellers = DB::table('event_tellers_view')
          ->where('event_id', $cash_out->event_id)
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

          broadcast(new CashOutUpdated($cash_outs));

          return response()->json(['success' => 'Cash-out approved successfully.']);
      } catch (\Exception $e) {
          DB::rollBack();

          return response()->json(['error' => 'Approval failed. Please try again.'], 500);
      }
  }


  public function declineCashOut(Request $request)
  {

      $cashOutId = $request->input('cash_out_id');

      $cashOut = CashOut::find($cashOutId);
      if (!$cashOut || $cashOut->cash_out_status !== 'Pending') {
          return response()->json(['error' => 'Invalid request'], 400);
      }

      DB::beginTransaction();
      try {
          // Atomic: only one request can take this out of Pending (no double approval),
          // and the teller balance changes in the same transaction.
          $newStatus = 'Declined';
          $won = CashOut::where('cash_out_id', $cashOut->cash_out_id)->where('cash_out_status', 'Pending')
            ->update(['cash_out_status' => $newStatus, 'admin_id' => session()->get('admin')->admin_id]);
          if ($won !== 1) {
            DB::rollBack();
            return response()->json(['error' => 'This request was already processed.'], 409);
          }
          if ($newStatus === 'Approved') {
            $ok = EventTeller::where('event_teller_id', $cashOut->event_teller_id)
              ->where('teller_balance', '>=', $cashOut->cash_out_amount)
              ->update(['teller_balance' => DB::raw('teller_balance - ' . (float) $cashOut->cash_out_amount)]);
            if ($ok !== 1) {
              DB::rollBack();
              return response()->json(['error' => 'Teller does not have enough cash on hand for this cash-out.'], 409);
            }
          }
          $cashOut->refresh();
          \App\Services\Ledger\TellerLedger::cashDecision('out', $cashOut, $newStatus);

          DB::commit();

          $cash_out = DB::table('cash_outs_view')
          ->where('cash_out_id', $cashOutId)
          ->first();

          $cash_outs = DB::table('cash_outs_view')
          ->where('event_id', $cash_out->event_id)
          ->orderBy('cash_out_id', 'desc')
          ->get();

          $event_teller = EventTeller::where('event_teller_id', $cashOut->event_teller_id)
          ->first();


          $this->createLog(session()->get('account_id'), "Web App", "Cash Transactions - Cash-out declined. " . $cashOut . " " . $event_teller );



          $event_tellers = DB::table('event_tellers_view')
          ->where('event_id', $cash_out->event_id)
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

          broadcast(new CashOutUpdated($cash_outs));

          return response()->json(['success' => 'Cash-out has been approved successfully!']);
      } catch (\Exception $e) {
          DB::rollBack();

          return response()->json(['error' => 'Approval failed. Please try again.'], 500);
      }
  }

  public function approvalViaApp(Request $request)
  {

      $cashOutId = $request->cash_out_id;
      if (!in_array($request->cash_out_status, ['Approved', 'Declined'], true)) {
          return response()->json(['success' => false, 'message' => 'Status must be Approved or Declined.'], 422);
      }
      $cashOut = CashOut::find($cashOutId);

      if (empty($cashOut)) {
          return response()->json([
            'success' => false,
            'message' => 'The request is invalid.']
            , 200);
      }

      if ($cashOut->cash_out_status <> 'Pending') {
        return response()->json([
          'success' => false,
          'message' => 'The request is invalid.']
          , 200);
    }



      DB::beginTransaction();
      try {
          // Atomic: only one request can take this out of Pending (no double approval),
          // and the teller balance changes in the same transaction.
          $newStatus = $request->cash_out_status;
          $won = CashOut::where('cash_out_id', $cashOut->cash_out_id)->where('cash_out_status', 'Pending')
            ->update(['cash_out_status' => $newStatus, 'admin_id' => $request->admin_id]);
          if ($won !== 1) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'This request was already processed.'], 200);
          }
          if ($newStatus === 'Approved') {
            $ok = EventTeller::where('event_teller_id', $cashOut->event_teller_id)
              ->where('teller_balance', '>=', $cashOut->cash_out_amount)
              ->update(['teller_balance' => DB::raw('teller_balance - ' . (float) $cashOut->cash_out_amount)]);
            if ($ok !== 1) {
              DB::rollBack();
              return response()->json(['success' => false, 'message' => 'Teller does not have enough cash on hand for this cash-out.'], 200);
            }
          }
          $cashOut->refresh();
          \App\Services\Ledger\TellerLedger::cashDecision('out', $cashOut, $newStatus);


          $admin = Admin::where('admin_id', $request->admin_id)->first();

          $this->createLog($admin->account_id, "Admin App", "Cash-out approval by Admin. " . json_encode($cashOut) . " " .  json_encode($admin));


          DB::commit();

          $cash_out = DB::table('cash_outs_view')
          ->where('cash_out_id', $cashOutId)
          ->first();

          $cash_outs = DB::table('cash_outs_view')
          ->where('event_id', $cash_out->event_id)
          ->orderBy('cash_out_id', 'desc')
          ->get();

          $event_teller = EventTeller::where('event_teller_id', $cash_out->event_teller_id)
          ->first();

          if($request->cash_out_status == 'Approved')
          {
          }


          $event_tellers = DB::table('event_tellers_view')
          ->where('event_id', $cash_out->event_id)
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

          broadcast(new CashOutUpdated($cash_outs));

          $res = response()->json([
            'cash_out' => $cash_out,
            'success' => true,
            'message' => 'Cash-out approved successfully.'
          ],200);


          return $res;

      } catch (\Exception $e) {
          DB::rollBack();

          return response()->json([
            'success' => false,
            'message' => 'Approval failed. Please try again.'
          ], 500);
      }
  }

  public function cashOutHistory(Request $r)
  {
      try {

          $history = DB::table('cash_outs_view')
              ->where('event_teller_id', $r->event_teller_id)
              ->orderBy('cash_out_datetime', 'desc')
              ->get();


          return response()->json([
              'success' => true,
              'message' => 'Cash-out history loaded successfully.',
              'data'    => $history
          ], 200);

      } catch (\Exception $e) {

          return response()->json([
              'success' => false,
              'message' => 'Unable to retrieve cash-out history.',
              'error'   => $e->getMessage()
          ], 500);
      }
  }

  public function cashOutHistoryAdmin(Request $r)
  {

      try {

        $event = Event::where('event_status', 'Active')->first();

          $history = DB::table('cash_outs_view')
              ->where('event_id', $event->event_id)
              ->orderBy('cash_out_datetime', 'desc')
              ->get();


          return response()->json([
              'success' => true,
              'message' => 'Cash-out history loaded successfully.',
              'data'    => $history
          ], 200);

      } catch (\Exception $e) {

          return response()->json([
              'success' => false,
              'message' => 'Unable to retrieve cash-out history.',
              'error'   => $e->getMessage()
          ], 500);
      }
  }
}
