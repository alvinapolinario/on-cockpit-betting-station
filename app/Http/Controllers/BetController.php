<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Admin;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Fight;
use App\Models\Teller;
use App\Models\EventTeller;
use App\Events\MatchUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;


class BetController extends Controller
{

  public function listAll(Request $r)
  {

    $bets = DB::table('bets_view')
    ->selectRaw("*, get_teller_name(event_teller_id) as teller_name, get_teller_name(bet_payout_by) as payout_name")
    ->where('event_id', $r->event_id)
    ->get();

    $claimed = $bets
    ->whereNotNull("bet_payout_by")
    ->where('bet_status', 1)
    ->sum('bet_payout_amount');

    $unclaimed = $bets
    ->whereNull("bet_payout_by")
    ->where('bet_status', 1)
    ->sum('bet_payout_amount');

    $total_bets = $bets
    ->sum('bet_amount');

    $valid_bets = $bets
    ->where('bet_status', 1)
    ->where('match_status', 'Completed')
    ->sum('bet_amount');

    $voided_bets = $bets
    ->where('bet_status', 2)
    ->sum('bet_amount');

    $draw_bets = $bets
    ->where('bet_status', 1)
    ->where('match_status', 'Draw')
    ->sum('bet_amount');

    $cancelled_bets = $bets
    ->where('bet_status', 1)
    ->where('match_status', 'Cancelled')
    ->sum('bet_amount');

    return response()->json([
        'bets' => $bets,
        'claimed' => $claimed,
        'unclaimed' => $unclaimed,
        'total_bets' => $total_bets,
        'valid_bets' => $valid_bets,
        'voided_bets' => $voided_bets,
        'draw_bets' => $draw_bets,
        'cancelled_bets' => $cancelled_bets,
        'returned_bets' => $voided_bets + $draw_bets + $cancelled_bets,
    ]);
  }

  public function listAllUnclaimed(Request $r)
  {

    $bets = DB::table('bets_view')
    ->selectRaw("*, get_teller_name(event_teller_id) as teller_name, get_teller_name(bet_payout_by) as payout_name")
    ->where('event_id', $r->event_id)
    ->where('bet_status', 1)
    ->where('bet_payout_amount' ,'>', 0)
    ->whereNull('bet_payout_by')
    ->get();

    $unclaimed = DB::table('bets_view')
    ->selectRaw("*, get_teller_name(event_teller_id) as teller_name, get_teller_name(bet_payout_by) as payout_name")
    ->where('event_id', $r->event_id)
    ->where('bet_status', 1)
    ->where('bet_payout_amount' ,'>', 0)
    ->whereNull('bet_payout_by')
    ->sum('bet_payout_amount');

    return response()->json([
        'bets' => $bets,
        'unclaimed' => $unclaimed,
    ]);

  }

  public function store(Request $r)
  {
    // Validate before touching anything. event_teller_id comes from the login token.
    $v = Validator::make($r->all(), [
      'side' => 'required|in:Meron,Wala',
      'amount' => 'required|numeric|min:' . config('betting.min_bet') . '|max:' . config('betting.max_bet'),
      'match_id' => 'required|integer',
      'client_request_id' => 'nullable|string|max:64|regex:/^[A-Za-z0-9-]+$/',
      'match_number' => 'nullable|integer',
    ]);
    if ($v->fails()) {
      return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
    }
    $amount = round((float) $r->amount, 2);
    $requestId = $r->client_request_id ?: null;

    // A resent request (the app never got our answer) gets the bet already saved,
    // even if the fight has closed since. Checked before any fight or side rule.
    if ($requestId !== null) {
      $replay = $this->replayBet((int) $r->event_teller_id, $requestId);
      if ($replay) {
        return $replay;
      }
    }
    $fail = function (string $message) {
      DB::rollBack();
      return response()->json(['success' => false, 'message' => $message]);
    };

    DB::beginTransaction();

    try {

      $event = Event::where('event_status', 'Active')->first();
      if (!$event) {
        return $fail('No active event available!');
      }

      // Lock the fight: bets on the same fight are processed one at a time,
      // so the pool totals and odds can never lose an update.
      $match = Fight::orderBy('match_id', 'desc')
      ->where('event_id', $event->event_id)
      ->where('match_bet_status', 'Open')
      ->where('match_status', 'Ongoing')
      ->lockForUpdate()
      ->first();

      if (!$match) {
        return $fail('No ongoing match found or bet status is not open.');
      }

      $teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();
      if (empty($teller)) {
        return $fail('Teller not found! Please relogin your account!');
      }

      $tel = Teller::where('teller_id', $teller->teller_id)->first();
      if (empty($tel->phone_uid)) {
        return $fail('Your account is not activated! Please contact administrator!');
      }

      // Clients that send a request id must name the open fight by its exact match_id,
      // plus the fight number they showed the customer, so a bet can never land on a
      // fight the teller did not see. Older clients may still send the match_number.
      if ($requestId !== null) {
        $shownNumber = (int) $r->match_number;
        if ($match->match_id != $r->match_id || ($shownNumber > 0 && $match->match_number != $shownNumber)) {
          return $fail("Fight #{$match->match_number} is now open. Check the fight and place the bet again.");
        }
      } elseif ($match->match_id != $r->match_id && $match->match_number != $r->match_id) {
        return $fail('Fight is already lapsed or not existing');
      }

      // Checked BEFORE the bet is created. No teller may bypass a closed side.
      if (($r->side === 'Meron' && $match->meron_bet_status == 1) || ($r->side === 'Wala' && $match->wala_bet_status == 1)) {
        return $fail("{$r->side} bet is currently disabled");
      }

      // Cryptographically random receipt digits, unique across all bets.
      do {
        $randomDigits = '';
        for ($i = 0; $i < config('betting.receipt_random_digits'); $i++) {
          $randomDigits .= random_int(0, 9);
        }
        $betReceiptCode = $teller->event_teller_id . $match->match_number . $randomDigits;
      } while (Bet::where('bet_receipt_code', $betReceiptCode)->exists());

      $bet = Bet::create([
        'event_teller_id' => $teller->event_teller_id,
        'match_id'        => $match->match_id,
        'bet_receipt_code'=> $betReceiptCode,
        'client_request_id' => $requestId,
        'bet_side'        => $r->side,
        'bet_amount'      => $amount,
        'bet_win_amount'  => 0,
        'bet_payout_amount'   => 0,
        'bet_payout_datetime' => null,
        'bet_status'      => '1',
        'bet_datetime'    => date('Y-m-d H:i:s'),
        'bet_is_winner'   => 0,
        'bet_void_datetime' => null,
        'bet_payout_by' => null
      ]);

      $this->createLog($tel->account_id, "Teller App", "Bet Created. " . $bet);

      $bet->match_number = $match->match_number;
      $bet->event_name = $event->event_name;
      $bet->teller_name = $tel->teller_name;

      if ($r->side === 'Meron') {
        $match->meron_total_bet += $amount;
      } else {
        $match->wala_total_bet += $amount;
      }

      if ($match->meron_total_bet > 0 && $match->wala_total_bet > 0) {
          $meronCalculated = $match->meron_total_bet - ($match->meron_total_bet * $event->event_percentage);
          $walaCalculated  = $match->wala_total_bet - ($match->wala_total_bet * $event->event_percentage);
          $totalComputed   = $meronCalculated + $walaCalculated;

          $match->meron_odds = round($totalComputed / $match->meron_total_bet, 2);
          $match->wala_odds  = round($totalComputed / $match->wala_total_bet, 2);
      } else {
          $match->meron_odds = 1;
          $match->wala_odds  = 1;
      }

      $match->save();

      // Atomic update: concurrent bets, payouts and cash movements cannot overwrite each other.
      EventTeller::where('event_teller_id', $teller->event_teller_id)->update([
        'teller_balance' => DB::raw('teller_balance + ' . $amount),
        'teller_match_balance' => DB::raw('teller_match_balance + ' . $amount),
      ]);
      \App\Services\Ledger\TellerLedger::record((int) $teller->event_teller_id, 'bet', $amount, 0, [
        'fight_no' => (int) $match->match_number, 'match_id' => (int) $match->match_id, 'reference' => $betReceiptCode,
        'note' => $r->side, 'source_table' => 'bets', 'source_id' => (int) $bet->bet_id,
      ]);

      DB::commit();

      broadcast(new MatchUpdated($match));

    $event_tellers = DB::table('event_tellers_view')
        ->where('event_id', $event->event_id)
        ->orderBy('teller_name', 'asc')
        ->get();

      foreach($event_tellers as &$tel) {
          $total_bet = DB::table('bets_view')->where('event_teller_id', $tel->event_teller_id)
          ->where('bet_status', 1)
          ->whereNotIn('match_status', ['Draw', 'Cancelled'])
          ->sum('bet_amount');

          $total_payout = Claim::where('event_teller_id', $tel->event_teller_id)->sum('claim_amount');

          $tel->total_bet = $total_bet;
          $tel->total_payout = $total_payout;
      }

      broadcast(new TellerBalanceUpdated($event_tellers));

      return response()->json([
        'success' => true,
        'message' => 'Bet created successfully!',
        'bet' => $bet
      ]);
    } catch (\Exception $e) {

      DB::rollBack();
      // Two copies of one request arrived together: the unique index stopped the
      // second insert, so answer it with the bet the first one saved.
      if ($requestId !== null && $e instanceof \Illuminate\Database\QueryException) {
        $replay = $this->replayBet((int) $r->event_teller_id, $requestId);
        if ($replay) {
          return $replay;
        }
      }
      Log::error('create-bet failed', ['exception' => $e]);
      return response()->json([
        'success' => false,
        'message' => 'Bet was not saved. Please try again.',
      ], 500);
    }
  }

  // The success response for a bet already saved under this teller's request id,
  // in the same shape as a new bet, or null when there is none.
  private function replayBet(int $eventTellerId, string $requestId)
  {
    $bet = Bet::where('event_teller_id', $eventTellerId)
      ->where('client_request_id', $requestId)
      ->first();
    if (!$bet) {
      return null;
    }

    $match = Fight::where('match_id', $bet->match_id)->first();
    $event = $match ? Event::where('event_id', $match->event_id)->first() : null;
    $tel = DB::table('event_tellers_view')->where('event_teller_id', $eventTellerId)->first();

    $bet->match_number = $match->match_number ?? null;
    $bet->event_name = $event->event_name ?? null;
    $bet->teller_name = $tel->teller_name ?? null;

    return response()->json([
      'success' => true,
      'duplicate' => true,
      'message' => 'Bet already saved.',
      'bet' => $bet
    ]);
  }


  public function reprint(Request $r)
  {
    $event = Event::where('event_status', 'Active')->first();

    if (!$event) {
      return response()->json([
        'success' => false,
        'message' => 'No active event available!'
      ]);
    }

    $bet = DB::table('bets_view')
    ->where('event_teller_id', $r->event_teller_id)
    ->orderBy('bet_id', 'desc')
    ->first();


    if(empty($bet))
    {
      return response()->json([
        'success' => false,
        'message' => 'No previous receipt found!'
      ]);
    }

    if ($bet->bet_status == 2) {
      return response()->json([
        'success' => false,
        'message' => 'Your last receipt was voided and cannot be reprinted.'
      ]);
    }

    // Same rule as reprint-teller: no reprints once the fight has a result.
    if (!Fight::where('match_id', $bet->match_id)->where('match_status', 'Ongoing')->exists()) {
      return response()->json([
        'success' => false,
        'message' => 'Cannot reprint the receipt because the fight has already finished.'
      ]);
    }


    // One reprint per receipt for tellers, recorded in bet_reprints.
    if ($previous = $this->recordTellerReprint($bet, (int) $r->event_teller_id)) {
      return $this->alreadyReprinted($previous);
    }

    $teller = DB::table('event_tellers_view')
    ->where('event_teller_id', $r->event_teller_id)
    ->first();


    $this->createLog($teller->account_id, "Teller App", "Bet receipt reprint. " . json_encode($bet));

    return response()->json([
      'success' => true,
      'message' => 'Bet reprinted successfully!',
      'bet' => $bet
    ]);
  }

  /**
   * Records a teller reprint of this receipt, or returns the earlier reprint when there is
   * one (a teller may reprint a receipt only once). The bet row is locked so two taps at
   * the same moment cannot both pass the check.
   */
  private function recordTellerReprint($bet, int $eventTellerId)
  {
    return DB::transaction(function () use ($bet, $eventTellerId) {
      Bet::where('bet_id', $bet->bet_id)->lockForUpdate()->first();
      $previous = DB::table('bet_reprints')->where('bet_id', $bet->bet_id)->orderBy('bet_reprint_id')->first();
      if ($previous) {
        return $previous;
      }
      DB::table('bet_reprints')->insert([
        'bet_id' => $bet->bet_id,
        'bet_receipt_code' => $bet->bet_receipt_code,
        'reprinted_via' => 'teller',
        'event_teller_id' => $eventTellerId,
        'reprinted_at' => date('Y-m-d H:i:s'),
      ]);
      return null;
    });
  }

  private function alreadyReprinted($previous)
  {
    $when = date('M j, Y h:i A', strtotime($previous->reprinted_at));
    return response()->json([
      'success' => false,
      'already_reprinted' => true,
      'message' => "This receipt was already reprinted once ({$when}). It cannot be reprinted again."
    ]);
  }

  public function reprintTeller(Request $r)
  {
    $event = Event::where('event_status', 'Active')->first();

    if (!$event) {
      return response()->json([
        'success' => false,
        'message' => 'No active event available!'
      ]);
    }

    $bet = DB::table('bets_view')
    ->where('bet_receipt_code', $r->bet_receipt_code)
    ->first();


    if(empty($bet))
    {
      return response()->json([
        'success' => false,
        'message' => 'No receipt found!'
      ]);
    }

    // A teller reprints only their own receipts: a reprint of someone else's winning
    // ticket could otherwise be scanned and paid out.
    if ((int) $bet->event_teller_id !== (int) $r->event_teller_id) {
      return response()->json([
        'success' => false,
        'message' => 'You can only reprint receipts you issued.'
      ]);
    }

    // A voided ticket is never printed again, so it cannot pass as a live bet.
    if ($bet->bet_status == 2) {
      return response()->json([
        'success' => false,
        'message' => 'This receipt was voided and cannot be reprinted.'
      ]);
    }

    $match = Fight::where('match_id', $bet->match_id)
    ->where('match_status', 'Ongoing')
    ->first();

    // Reprints only while the fight is ongoing: once it has a result, a reprinted winning
    // ticket could be scanned and paid without the customer's original.
    if(empty($match))
    {
      return response()->json([
          'success' => false,
          'message' => 'Cannot reprint the receipt because the fight has already finished.'
      ], 200);
    }


    // One reprint per receipt for tellers, recorded in bet_reprints.
    if ($previous = $this->recordTellerReprint($bet, (int) $r->event_teller_id)) {
      return $this->alreadyReprinted($previous);
    }

    $teller = DB::table('event_tellers_view')
    ->where('event_teller_id', $r->event_teller_id)
    ->first();


    $this->createLog($teller->account_id, "Teller App", "Bet receipt reprint. " . json_encode($bet));

    return response()->json([
      'success' => true,
      'message' => 'Bet reprinted successfully!',
      'bet' => $bet
    ]);
  }

  public function reprintAdmin(Request $r)
  {
    $event = Event::where('event_status','Active')->first();

    if(empty($event))
    {
      return response()->json([
        'success' => false,
        'message' => 'This event is no longer active.',
      ]);
    }

    $bet = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_receipt_code', $r->bet_receipt_code)
    ->first();

    if(empty($bet))
    {
      return response()->json([
        'success' => false,
        'message' => 'Reprint failed: The bet receipt code does not exist.',
      ]);
    }

    // Same rules as the teller reprint: a voided ticket is never printed again, and no
    // reprints once the fight has a result (a reprinted winning ticket could be scanned
    // and paid without the customer's original).
    if ($bet->bet_status == 2) {
      return response()->json([
        'success' => false,
        'message' => 'Reprint failed: This receipt was voided and cannot be reprinted.',
      ]);
    }
    if (!Fight::where('match_id', $bet->match_id)->where('match_status', 'Ongoing')->exists()) {
      return response()->json([
        'success' => false,
        'message' => 'Reprint failed: The fight has already finished.',
      ]);
    }

    // Admin reprints are recorded (and count as the receipt's reprint in the teller app)
    // but not limited: an admin can still reprint a receipt a teller already reprinted.
    DB::table('bet_reprints')->insert([
      'bet_id' => $bet->bet_id,
      'bet_receipt_code' => $bet->bet_receipt_code,
      'reprinted_via' => 'admin',
      'admin_id' => $r->admin_id,
      'reprinted_at' => date('Y-m-d H:i:s'),
    ]);

    $admin = Admin::where('admin_id', $r->admin_id)->first();

    $this->createLog($admin->account_id, "Admin App", "Reprint receipt by Admin. " . json_encode($bet));


    return response()->json([
      'success' => true,
      'message' => 'Reprint completed successfully.',
      'bet' => $bet
    ]);
  }


  public function betHistory(Request $request)
  {

      // Only the latest fight this teller has receipts on; earlier fights stay hidden.
      $tellerId = $request->event_teller_id;
      $latestMatchId = DB::table('bets_view')
          ->where('event_teller_id', $tellerId)
          ->where('event_status', 'Active')
          ->max('match_id');
      if ($latestMatchId === null) {
          return response()->json([]);
      }
      $bets = DB::table('bets_view')
          ->select('bets_view.*', DB::raw('(select count(*) from bet_reprints r where r.bet_id = bets_view.bet_id) as reprint_count'))
          ->where('event_teller_id', $tellerId)
          ->where('event_status', 'Active')
          ->where('match_id', $latestMatchId)
          ->orderBy('bet_datetime', 'desc')
          ->get();

      return response()->json($bets);
  }

  public function betHistoryOngoing(Request $request)
  {

      $tellerId = $request->event_teller_id;
      $bets = DB::table('bets_view')
          ->where('event_teller_id', $tellerId)
          ->where('match_status', 'Ongoing')
          ->orderBy('bet_datetime', 'desc')
          ->get();

      return response()->json($bets);
  }


  public function unclaimedReceipts(Request $request)
  {

    $tellerId = $request->event_teller_id;
    $bets = DB::table('bets_view')
        ->where('event_teller_id', $tellerId)
        ->where('bet_payout_amount', '>', 0)
        ->where('event_status', 'Active')
        ->where('bet_status', 1)
        // ->whereIn('match_status', ['Draw', 'Cancelled'])
        ->whereNull('bet_payout_by')
        ->orderBy('bet_datetime', 'desc')
        ->get();

    return response()->json($bets);
  }


  public function voidBet(Request $r)
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
        // Lock the bet row: two voids (or a void and a payout) of the same
        // receipt at the same moment cannot both succeed.
        $bet = Bet::where('bet_receipt_code', (string) $r->receipt_code)->lockForUpdate()->first();

        if (empty($bet)) {
          return $fail('Bet does not exist!');
        }
        if ((int) $bet->event_teller_id !== (int) $r->event_teller_id) {
          return $fail('You cannot void this bet. Please tell the bettor to go the teller who made this bet!');
        }
        if ($bet->bet_status == 2) {
          return $fail('Bet has already been voided!');
        }
        if (Claim::where('bet_id', $bet->bet_id)->exists()) {
          return $fail('Cannot void the receipt because its winnings have already been claimed!');
        }

        $match = Fight::where('match_id', $bet->match_id)
        ->where('match_status', 'Ongoing')
        ->lockForUpdate()
        ->first();

        if (empty($match)) {
          return $fail('Cannot void the receipt because the fight has already finished!');
        }
        if ($match->match_bet_status == 'Closed') {
          return $fail('Cannot void the receipt because the fight is already closed.');
        }

        $bet->bet_status = 2;
        $bet->bet_void_datetime = date('Y-m-d H:i:s');
        $bet->bet_payout_amount = $bet->bet_amount;
        $bet->bet_payout_datetime = $time_now;
        $bet->bet_payout_by = $r->event_teller_id;
        $bet->save();

        // Atomic balance update.
        EventTeller::where('event_teller_id', $r->event_teller_id)->update([
          'teller_balance' => DB::raw('teller_balance - ' . (float) $bet->bet_amount),
          'teller_match_balance' => DB::raw('teller_match_balance - ' . (float) $bet->bet_amount),
        ]);
        \App\Services\Ledger\TellerLedger::record((int) $r->event_teller_id, 'void', 0, $bet->bet_amount, [
          'fight_no' => (int) $match->match_number, 'match_id' => (int) $match->match_id, 'reference' => $bet->bet_receipt_code,
          'note' => 'Voided ' . $bet->bet_side . ' bet; money returned to the bettor', 'source_table' => 'bets', 'source_id' => (int) $bet->bet_id,
        ]);
        $event_teller = EventTeller::where('event_teller_id', $r->event_teller_id)->first();

        if($bet->bet_side == 'Meron')
        {
          $match->meron_total_bet = $match->meron_total_bet - $bet->bet_amount;
        }

        if($bet->bet_side == 'Wala')
        {
          $match->wala_total_bet = $match->wala_total_bet - $bet->bet_amount;
        }


        if ($match->meron_total_bet > 0 && $match->wala_total_bet > 0)
        {
          $event = Event::where('event_id', $event_teller->event_id)
          ->first();

          $meron_calculated_bet = $match->meron_total_bet - ($match->meron_total_bet * $event->event_percentage);
          $wala_calculated_bet = $match->wala_total_bet - ($match->wala_total_bet * $event->event_percentage);

          $computed_total = $meron_calculated_bet + $wala_calculated_bet;


          $match->meron_odds = round($computed_total / $match->meron_total_bet, 2);
          $match->wala_odds  = round($computed_total / $match->wala_total_bet, 2);

        }
        else
        {

          $match->meron_odds = 1;
          $match->wala_odds  = 1;

        }


        $match->save();

        $teller = Teller::where('teller_id', $event_teller->teller_id)->first();

        $this->createLog($teller->account_id, "Teller App", "Bet voided. " . json_encode($bet));


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

          broadcast(new MatchUpdated($match));

          $bet = DB::table('bets_view')
          ->where('bet_id', $bet->bet_id)
          ->first();

          return response()->json([
            'bet' => $bet,
              'success' => true,
              'message' => 'Receipt voided successfully!'
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

  public function voidedBets(Request $r)
  {

    try {

      $history = DB::table('bets_view')
          ->where('event_teller_id', $r->event_teller_id)
          ->where('bet_status', 2)
          ->orderBy('bet_void_datetime', 'desc')
          ->get();

      return response()->json([
          'success' => true,
          'message' => 'Payout history retrieved successfully!',
          'data'    => $history
      ], 200);

  } catch (\Exception $e) {

      return response()->json([
          'success' => false,
          'message' => 'Failed to retrieve payout history.',
          'error'   => $e->getMessage()
      ], 500);
  }
  }

  public function history(Request $request)
  {

      $eventTellerId = $request->event_teller_id;

      // First, get all matches with total summaries
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


      // For each match, get individual bets
      $detailed = [];

      foreach ($matches as $match) {
          $individualBets = DB::table('bets_view')
              ->where('event_teller_id', $eventTellerId)
              ->where('match_id', $match->match_id)
              ->select('bet_receipt_code', 'bet_side', 'bet_amount', 'bet_datetime', 'bet_status', 'bet_is_winner', 'bet_win_amount', 'bet_payout_amount','bet_payout_datetime', 'bet_payout_by')
              ->orderBy('bet_datetime')
              ->get();

            foreach($individualBets as &$bet)
            {
              if($bet->bet_payout_amount > 0)
              {
                $bet->can_payout = 1;
                $payout_by = DB::table('event_tellers_view')->where('event_teller_id', $bet->bet_payout_by)->first();
                $bet->teller_name = ($payout_by->teller_name ?? 'Unclaimed');
              }
              else
              {
                $bet->can_payout = 0;
                $bet->teller_name = '';
              }
            }

          $detailed[] = [
              'match_status' => $match->match_status,
              'match_winner' => $match->match_winner,
              'wala_odds' => $match->wala_odds,
              'meron_odds' => $match->meron_odds,
              'match_number' => $match->match_number,
              'total_meron' => $match->total_meron,
              'total_wala' => $match->total_wala,
              'total_bet' => $match->total_bet,
              'bets' => $individualBets
          ];
      }

      $res = response()->json($detailed);


      return $res;
  }



}
