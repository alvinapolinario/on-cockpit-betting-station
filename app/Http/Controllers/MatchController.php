<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Fight;
use App\Models\EventTeller;
use App\Events\MatchUpdated;
use Illuminate\Http\Request;
use App\Services\Bridge\MatchingBridge;
use App\Events\MatchesUpdated;
use PhpParser\Node\Expr\Match_;
use App\Events\TVDisplayUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;
use App\Models\AdminRemittance;
use App\Models\Salary;

class MatchController extends Controller
{
  public function index()
  {
    $event = Event::where('event_status', 'Active')->first();

    $this->createLog(session()->get('account_id'), "Web App", "Opened the Matches page");

    return view('matches')
    ->with('event', $event);
  }

  public function list()
  {
    $event = Event::where('event_status', 'Active')->first();

    if (empty($event)) {
      return response()->json([]);
    }

    $match = Fight::orderBy('match_id', 'desc')
      ->where('event_id', $event->event_id)
      ->first();

    if (empty($match)) {
      self::newStaticMatch();
    }

    return Fight::orderBy('match_id', 'desc')
      ->where('event_id', $event->event_id)
      ->get();
  }

  public function listApp()
  {
    return $this->fightHistory();
  }

  public function getStartedApp()
  {
    $event = Event::where('event_status', 'Active')->first();
    if (empty($event)) {
      return response()->json([
        'success' => false,
        'message' => 'No active event',
        'match' => null,
        'matches' => [],
      ], 400);
    }

    $this->getStarted();
    return $this->fightHistory();
  }

  public function getStarted()
  {
    $event = Event::where('event_status', 'Active')->first();
    if (empty($event)) {
      return;
    }
    $match = Fight::orderBy('match_id', 'desc')
        ->where('event_id', $event->event_id)
        ->first();

        // With the matching link on, the first fight comes from matching's Call (no placeholder).
        if(empty($match) && !MatchingBridge::enabled())
        {
          $this->newStaticMatch();
          $match = Fight::orderBy('match_id', 'desc')
          ->where('event_id', $event->event_id)
          ->first();
        }

        if (empty($match)) {
          // Matching link on and nothing called yet: the board waits for matching's Call.
          $this->createLog(session()->get('account_id'), "Web App", "Matches - Clicked the Get Started button (waiting for a fight from matching).");
          return response()->json(['message' => 'Waiting for the matching operator to call the first fight.']);
        }

        broadcast(new MatchUpdated($match));


        $matches = Fight::orderBy('match_id', 'desc')
          ->where('event_id', $event->event_id)
          ->get();

       broadcast(new MatchesUpdated($matches));

       $this->createLog(session()->get('account_id'), "Web App", "Matches - Clicked the Get Started button.");

  }

  public function fightHistory()
  {
    $event = Event::where('event_status', 'Active')->first();

    if (empty($event)) {
      return response()->json([
        'success' => false,
        'message' => 'No active event',
        'match' => null,
        'matches' => [],
      ]);
    }

    $match = Fight::orderBy('match_id', 'desc')
      ->where('event_id', $event->event_id)
      ->first();

    if (empty($match)) {
      $match = self::newStaticMatch();
    }

    $matches = Fight::orderBy('match_id', 'desc')
      ->where('event_id', $event->event_id)
      ->get();

    return response()->json([
      'success' => true,
      'match' => $match,
      'matches' => $matches,
    ]);
  }

  public function getLatestMatch() {
    $event = Event::where('event_status', 'Active')->first();
    if (empty($event)) {
        return response()->json(['success' => false], 200);
    }
    $match = Fight::orderBy('match_id', 'desc')
        ->where('event_id', $event->event_id)
        ->first();

    if (!$match) {
        return response()->json(['success' => false], 200);
    }

    return response()->json([
        'success' => true,
        'match_id' => $match->match_id,
        'match_number' => $match->match_number,
        'match_bet_status' => $match->match_bet_status
    ]);
}

  public function newMatch()
  {
    if (MatchingBridge::enabled()) {
      return 0; // fights are called from the matching system
    }
    DB::beginTransaction();

    try {
        $event = Event::where('event_status', 'Active')->first();
        $match = Fight::orderBy('match_number', 'desc')
            ->where('event_id', $event->event_id)
            ->first();

            EventTeller::where('event_id', $event->event_id)
            ->update(['teller_match_balance' => 0]);


      $match =  Fight::create([
        'event_id' => $event->event_id,
        'match_number' => ($match->match_number ?? 0) + 1,
        'match_winner' => '',
        'meron_total_bet' => 0,
        'wala_total_bet' => 0,
        'meron_odds' => 0,
        'wala_odds' => 0,
        'meron_bet_status' => 0,
        'wala_bet_status' => 0,
        'match_bet_status' => 'Open',
        'match_status' => 'Ongoing',
        'match_created_datetime' => date('Y-m-d H:i:s'),
        'is_display' => 0,
      ]);

      DB::commit();

      broadcast(new MatchUpdated($match));

      $event_tellers = DB::table('event_tellers_view')
      ->where('event_id', $event->event_id)
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

      return $match->match_number;

    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }

  public static function newStaticMatch()
  {
    DB::beginTransaction();



    try {
        $event = Event::where('event_status', 'Active')->first();
        $match = Fight::orderBy('match_number', 'desc')
            ->where('event_id', $event->event_id)
            ->first();


            EventTeller::where('event_id', $event->event_id)
            ->update(['teller_match_balance' => 0]);

      $match =  Fight::create([
        'event_id' => $event->event_id,
        'match_number' => ($match->match_number ?? 0) + 1,
        'match_winner' => '',
        'meron_total_bet' => 0,
        'wala_total_bet' => 0,
        'meron_odds' => 0,
        'wala_odds' => 0,
        'meron_bet_status' => 0,
        'wala_bet_status' => 0,
        'match_status' => 'Ongoing',
        'match_bet_status' => 'Closed',
        'match_created_datetime' => date('Y-m-d H:i:s'),
        'is_display' => 0,
      ]);

      DB::commit();

      broadcast(new MatchUpdated($match));

      $event_tellers = DB::table('event_tellers_view')
      ->where('event_id', $event->event_id)
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

      return $match;

    } catch (\Exception $e) {
      DB::rollBack();
      return null;
    }
  }


  public function toggleMeron(Request $r)
  {
    $guard = Fight::where('match_id', $r->match_id)->first();
    if (!$guard) {
      return response()->json(['message' => 'Fight not found.'], 404);
    }
    if ($guard->match_status !== 'Ongoing') {
      return response()->json(['message' => "Fight #{$guard->match_number} is already settled; betting can no longer be changed."], 422);
    }
    if (Event::where('event_id', $guard->event_id)->value('event_status') !== 'Active') {
      return response()->json(['message' => 'This event is not active.'], 422);
    }
    DB::beginTransaction();

    try {
      $match = Fight::where('match_id', $r->match_id)->firstOrFail();

      $event = Event::where('event_id', $match->event_id)->first();


      $sum_meron = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Meron')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $sum_wala = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Wala')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $match->meron_total_bet = $sum_meron;
      $match->wala_total_bet = $sum_wala;


      $meron_calculated_bet = $match->meron_total_bet - ($match->meron_total_bet * $event->event_percentage);
      $wala_calculated_bet = $match->wala_total_bet - ($match->wala_total_bet * $event->event_percentage);

      $computed_total = $meron_calculated_bet + $wala_calculated_bet;


      if($sum_meron > 0 and $sum_wala > 0)
      {
        $match->meron_odds = round($computed_total / $match->meron_total_bet, 2);
        $match->wala_odds  = round($computed_total / $match->wala_total_bet, 2);
      }


      $match->meron_bet_status = !$match->meron_bet_status;
      $match->save();

      $this->createLog(session()->get('account_id'), "Web App", "Matches - Fight #" . $match->match_number . " Toggled the Bet Side Status of Meron. " . $match);

      DB::commit();


      broadcast(new TVDisplayUpdated($match));
      broadcast(new MatchUpdated($match));



      return response()->json([
          'message' => 'Meron bet toggled!',
      ]);

    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json(['message' => $e->getMessage()], 500);
    }
  }

  public function toggleWala(Request $r)
  {
    $guard = Fight::where('match_id', $r->match_id)->first();
    if (!$guard) {
      return response()->json(['message' => 'Fight not found.'], 404);
    }
    if ($guard->match_status !== 'Ongoing') {
      return response()->json(['message' => "Fight #{$guard->match_number} is already settled; betting can no longer be changed."], 422);
    }
    if (Event::where('event_id', $guard->event_id)->value('event_status') !== 'Active') {
      return response()->json(['message' => 'This event is not active.'], 422);
    }
    DB::beginTransaction();

    try {
      $match = Fight::where('match_id', $r->match_id)->firstOrFail();
      $event = Event::where('event_id', $match->event_id)->first();

      $sum_meron = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Meron')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $sum_wala = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Wala')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $match->meron_total_bet = $sum_meron;
      $match->wala_total_bet = $sum_wala;


      $meron_calculated_bet = $match->meron_total_bet - ($match->meron_total_bet * $event->event_percentage);
      $wala_calculated_bet = $match->wala_total_bet - ($match->wala_total_bet * $event->event_percentage);

      $computed_total = $meron_calculated_bet + $wala_calculated_bet;


      if($sum_meron > 0 and $sum_wala > 0)
      {
        $match->meron_odds = round($computed_total / $match->meron_total_bet, 2);
        $match->wala_odds  = round($computed_total / $match->wala_total_bet, 2);
      }


      $match->wala_bet_status = !$match->wala_bet_status;
      $match->save();

      $this->createLog(session()->get('account_id'), "Web App", "Matches - Fight #" . $match->match_number . " Toggled the Bet Side Status of Meron. " . $match);

      DB::commit();

      broadcast(new TVDisplayUpdated($match));
      broadcast(new MatchUpdated($match));

      return response()->json([
          'message' => 'Wala bet toggled!',
      ]);

    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json(['message' => $e->getMessage()], 500);
    }
  }


  public function updateMatchBetStatus(Request $r)
  {
    if (!in_array($r->match_bet_status, ['Open', 'Closed'], true)) {
      return response()->json(['message' => 'Betting status must be Open or Closed.'], 422);
    }
    $guard = Fight::where('match_id', $r->match_id)->first();
    if (!$guard) {
      return response()->json(['message' => 'Fight not found.'], 404);
    }
    if ($guard->match_status !== 'Ongoing') {
      return response()->json(['message' => "Fight #{$guard->match_number} is already settled; betting can no longer be changed."], 422);
    }
    if (Event::where('event_id', $guard->event_id)->value('event_status') !== 'Active') {
      return response()->json(['message' => 'This event is not active.'], 422);
    }
    DB::beginTransaction();

    try {
      Fight::query()->update(['is_display' => 0]);

      $match = Fight::where('match_id', $r->match_id)->firstOrFail();
      $event = Event::where('event_id', $match->event_id)->first();


      $sum_meron = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Meron')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $sum_wala = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Wala')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $match->meron_total_bet = $sum_meron;
      $match->wala_total_bet = $sum_wala;


      $meron_calculated_bet = $match->meron_total_bet - ($match->meron_total_bet * $event->event_percentage);
      $wala_calculated_bet = $match->wala_total_bet - ($match->wala_total_bet * $event->event_percentage);

      $computed_total = $meron_calculated_bet + $wala_calculated_bet;


      if($sum_meron > 0 and $sum_wala > 0)
      {
        $match->meron_odds = round($computed_total / $match->meron_total_bet, 2);
        $match->wala_odds  = round($computed_total / $match->wala_total_bet, 2);
      }


      if ($r->match_bet_status === 'Open' && $match->hold_reason) {
        DB::rollBack();
        return response()->json(['message' => "Fight #{$match->match_number} is on HOLD ({$match->hold_reason}). Matching must fix and re-send it first."], 422);
      }

      $match->match_bet_status = $r->match_bet_status;
      $match->meron_bet_status = 0;
      $match->wala_bet_status = 0;

      // Entries of a fight called from matching come from matching, not from this form.
      if (!MatchingBridge::fightLinked($match)) {
        $match->meron_entry = $r->meron_entry;
        $match->wala_entry = $r->wala_entry;
      }
      if ($r->match_bet_status === 'Open' && !$match->bet_opened_at) {
        $match->bet_opened_at = now(); // from now on matching can no longer change this fight
      }

      $match->save();
      MatchingBridge::queueStatus($match, strtolower($match->match_bet_status));

      $this->createLog(session()->get('account_id'), "Web App", "Matches - " . 'Fight #' . $match->match_number . " bet status updated to " . $match->match_bet_status . "!" . $match);


      broadcast(new MatchUpdated($match));
      broadcast(new TVDisplayUpdated($match));

      DB::commit();
      $this->flushBridgeLater();



      return response()->json([
          'message' => 'Fight #' . $match->match_number . " bet status updated to " . $match->match_bet_status . "!",
      ]);

    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json(['message' => $e->getMessage()], 500);
    }
  }

  public function updateMatchStatus(Request $r)
  {
    $settled = ['Completed', 'Draw', 'Cancelled'];
    $status = (string) $r->match_status;
    $winner = (string) $r->winner;
    if (!in_array($status, $settled, true)) {
      return response()->json(['message' => 'Invalid match status.'], 422);
    }
    if ($status === 'Completed' && !in_array($winner, ['Meron', 'Wala', 'Draw', 'Cancelled'], true)) {
      return response()->json(['message' => 'Choose Meron, Wala, Draw or Cancelled.'], 422);
    }
    if ($status !== 'Completed' && !in_array($winner, ['', '-', 'Draw', 'Cancelled'], true)) {
      return response()->json(['message' => 'A draw or cancelled fight has no winner.'], 422);
    }

    $target = Fight::where('match_id', $r->match_id)->first();
    if (!$target) {
      return response()->json(['message' => 'Fight not found.'], 404);
    }
    if (Event::where('event_id', $target->event_id)->value('event_status') !== 'Active') {
      return response()->json(['message' => 'This event is not active; its results can no longer be changed.'], 422);
    }

    $isCorrection = in_array($target->match_status, $settled, true);
    $previousResult = $target->match_status . ($target->match_winner ? ' / ' . $target->match_winner : '');

    if (!$isCorrection && config('betting.require_closed_before_result') && $target->match_bet_status !== 'Closed') {
      return response()->json(['message' => "Close betting on Fight #{$target->match_number} before declaring the result."], 422);
    }

    if ($isCorrection) {
      // Only the latest settled fight may be corrected...
      $laterSettled = Fight::where('event_id', $target->event_id)
        ->whereIn('match_status', $settled)
        ->where('match_id', '>', $target->match_id) // play order (fights may be played out of number order)
        ->exists();
      if ($laterSettled) {
        return response()->json(['message' => "Fight #{$target->match_number} is locked: only the latest settled fight can be corrected."], 422);
      }
      // ...and only while none of its winnings or refunds have been paid.
      $paid = DB::table('claims')->join('bets', 'bets.bet_id', '=', 'claims.bet_id')->where('bets.match_id', $target->match_id)->exists();
      if ($paid) {
        return response()->json(['message' => "Fight #{$target->match_number} already has payouts, so its result can no longer be changed. Report this as an incident."], 422);
      }
      // A correction never creates another fight.
      $r->merge(['dont_create' => 1]);
    }


    // With the matching link on, fights are called from matching: never create the next one here.
    if (MatchingBridge::enabled()) {
      $r->merge(['dont_create' => 1]);
    }

    if($r->winner == 'Draw' || $r->winner == 'Cancelled')
    {
      $r->match_status = $r->winner;
      $r->winner = '';

    }


    DB::beginTransaction();


    try {
      $match = Fight::where('match_id', $r->match_id)->firstOrFail();
      $event = Event::where('event_id', $match->event_id)->first();

      $sum_meron = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Meron')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $sum_wala = Bet::where('match_id', $r->match_id)
      ->where('bet_side', 'Wala')
      ->where('bet_status', 1)
      ->sum('bet_amount');

      $match->meron_total_bet = $sum_meron;
      $match->wala_total_bet = $sum_wala;


      $meron_calculated_bet = $match->meron_total_bet - ($match->meron_total_bet * $event->event_percentage);
      $wala_calculated_bet = $match->wala_total_bet - ($match->wala_total_bet * $event->event_percentage);

      $computed_total = $meron_calculated_bet + $wala_calculated_bet;


      if($sum_meron > 0 and $sum_wala > 0)
      {
        $match->meron_odds = round($computed_total / $match->meron_total_bet, 2);
        $match->wala_odds  = round($computed_total / $match->wala_total_bet, 2);
      }

      $match_number = $match->match_number;

      $match->match_status = $r->match_status;

      if($r->match_status == 'Completed') {
        $match->match_winner = $r->winner;

        // Reset all winners first (important for changed results)
        Bet::where('match_id', $r->match_id)
            ->where('bet_status', 1)
            ->update([
                'bet_win_amount' => 0,
                'bet_payout_amount' => 0,
                'bet_is_winner' => 0,
            ]);

        // Apply winner side logic
        $bets = Bet::where('match_id', $r->match_id)
            ->where('bet_side', $r->winner)
            ->where('bet_status', 1)
            ->get();

        foreach($bets as $bet) {
            $odds = $r->winner === 'Meron' ? $match->meron_odds : $match->wala_odds;
            $bet->bet_win_amount = floor($bet->bet_amount * $odds);
            $bet->bet_payout_amount = floor($bet->bet_win_amount);
            $bet->bet_is_winner = 1;
            $bet->save();
        }
    }

      elseif($r->match_status == 'Cancelled')
      {
        $match->match_winner = 'N/A';

        $bets = Bet::where('match_id', $r->match_id)
        ->where('bet_status', 1)
        ->get();

        foreach($bets as $bet)
        {
          $bet->bet_win_amount = 0;
          $bet->bet_payout_amount = $bet->bet_amount;
          $bet->bet_is_winner = 0;
          $bet->save();
        }

      }
      elseif($r->match_status == 'Draw')
      {
        $match->match_winner = 'N/A';

        $bets = Bet::where('match_id', $r->match_id)
        ->where('bet_status', 1)
        ->get();

        foreach($bets as $bet)
        {
          $bet->bet_win_amount = 0;
          $bet->bet_payout_amount = $bet->bet_amount;
          $bet->bet_is_winner = 0;
          $bet->save();
        }


      }

      $match->is_display = 1;

      $match->save();

      $bridgeResult = $match->match_status === 'Completed' ? strtolower((string) $match->match_winner) : strtolower((string) $match->match_status);
      MatchingBridge::queueResult($match, $bridgeResult, $isCorrection ? $previousResult : null,
        DB::table('accounts')->where('account_id', session()->get('account_id') ?? $r->attributes->get('account_id'))->value('username'));

      $actor = session()->get('account_id') ?? $r->attributes->get('account_id');
      $channel = $r->attributes->get('token_role') ? 'Admin App' : 'Web App';
      $this->createLog($actor, $channel, "Matches - " . 'Fight #' .  $match_number . " match status updated to " . $r->match_status . "!" . $match);
      if ($isCorrection) {
        $this->createLog($actor, $channel, "RESULT CORRECTION: Fight #{$match_number} changed from {$previousResult} to " . $r->match_status . ($match->match_winner && $match->match_winner !== 'N/A' ? ' / ' . $match->match_winner : ''));
      }

      broadcast(new TVDisplayUpdated($match));

      DB::commit();
      $this->flushBridgeLater();

      if(empty($r->dont_create))
      {
        $match = $this->newStaticMatch();
      }

      broadcast(new MatchUpdated($match));

      $event = Event::where('event_status', 'Active')->first();
      $matches = Fight::orderBy('match_id', 'desc')
          ->where('event_id', $event->event_id)
          ->get();

      broadcast(new MatchesUpdated($matches));

      return response()->json([
          'message' => 'Fight #' .  $match_number . " match status updated to " . $r->match_status . "!",
      ]);

    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json(['message' => $e->getMessage()], 500);
    }
  }

  /** HOLD: the cocks in the pit do not match the called fight. Only before betting opens. */
  public function hold(Request $r)
  {
    $reason = trim((string) $r->input('reason'));
    if ($reason === '' || mb_strlen($reason) > 200) {
      return response()->json(['message' => 'Give a short reason for the hold (max 200 characters).'], 422);
    }
    $match = Fight::where('match_id', $r->match_id)->first();
    if (!$match) return response()->json(['message' => 'Fight not found.'], 404);
    if (!MatchingBridge::fightLinked($match)) return response()->json(['message' => 'Only fights called from the matching system can be put on hold.'], 422);
    if ($match->match_status !== 'Ongoing' || $match->bet_opened_at) {
      return response()->json(['message' => "Fight #{$match->match_number}: betting already opened. Cancel the fight instead (bets are refunded)."], 422);
    }

    DB::transaction(function () use ($match, $reason) {
      $match->hold_reason = mb_substr($reason, 0, 200);
      $match->save();
      MatchingBridge::queueStatus($match, 'held', $match->hold_reason);
      $this->createLog(session()->get('account_id'), 'Web App', "Matches - Fight #{$match->match_number} put on HOLD: {$match->hold_reason}");
    });
    $this->flushBridgeLater();
    broadcast(new MatchUpdated($match));

    return response()->json(['message' => "Fight #{$match->match_number} is on hold and was sent back to matching."]);
  }

  /** Link status for the operator screen. */
  public function bridgeStatus()
  {
    return response()->json(MatchingBridge::status());
  }

  /** Deliver queued matching messages right after the response is sent (the worker retries anything left). */
  private function flushBridgeLater(): void
  {
    if (!MatchingBridge::enabled()) return;
    app()->terminating(function () {
      try { MatchingBridge::flush(); } catch (\Throwable $e) { report($e); }
    });
  }

  public function updatedisplay(Request $r)
  {

    Fight::query()->update(['is_display' => 0]);


    $match = Fight::where('match_id', $r->match_id)
    ->first();

    $match->is_display = $r->is_display;

    $match->save();

    $this->createLog(session()->get('account_id'), "Web App", "Matches - " . 'Fight #' .  $match->match_number . " TV display status updated successfully.!" . $match);


    broadcast(new TVDisplayUpdated($match));




    return response()->json(['message' => 'TV display status updated successfully.']);

  }

  public function report(Request $r)
  {
    $event = Event::where('event_id', $r->event_id)->first();

    $total_bets_meron = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_status', 1)
    ->where('bet_side','Meron')
    ->where('match_status', 'Completed')
    ->sum('bet_amount');


    $total_bets_wala = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_status', 1)
    ->where('bet_side','Wala')
    ->where('match_status', 'Completed')
    ->sum('bet_amount');

    $total_collection = ($total_bets_meron + $total_bets_wala);


    $teller_count = DB::table('event_tellers_view')
    ->where('event_id', $event->event_id)
    ->count();

    $fight_count = Fight::where('event_id', $event->event_id)
    ->count();

    $fight_count_completed = Fight::where('event_id', $event->event_id)
    ->where('match_status', 'Completed')
    ->count();


    $fight_count_draw = Fight::where('event_id', $event->event_id)
    ->where('match_status', 'Draw')
    ->count();


    $fight_count_cancelled = Fight::where('event_id', $event->event_id)
    ->where('match_status', 'Cancelled')
    ->count();


    $bet_count = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_status', 1)
    ->count();

    $bet_count_meron = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_status', 1)
    ->where('bet_side', 'Meron')
    ->count();

    $bet_count_wala = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_status', 1)
    ->where('bet_side', 'Wala')
    ->count();

    $voided_bets = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->where('bet_status', 2)
    ->sum('bet_amount');

    $claimed_bets = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->whereNotNull('bet_payout_datetime')
    ->where('bet_payout_amount','>',0)
    ->where('bet_status', 1)
    ->sum('bet_payout_amount');

    $unclaimed_winnings = DB::table('bets_view')
    ->where('event_id', $event->event_id)
    ->whereNull('bet_payout_datetime')
    ->where('bet_payout_amount','>',0)
    ->where('bet_status', 1)
    ->sum('bet_payout_amount');

    $total_salary = 0;

    $salaries = Salary::where('event_id', $event->event_id)->get();
    foreach($salaries as $salary)
    {
       $total_salary += $salary->daily_rate + ($salary->overtime_rate * $salary->total_overtime) + $salary->bonus;
    }



    $cash_in = DB::table('cash_ins_view')
    ->where('event_id', $event->event_id)
    ->where('cash_in_status', 'Approved')
    ->sum('cash_in_amount');

    $cash_out = DB::table('cash_outs_view')
    ->where('event_id', $event->event_id)
    ->where('cash_out_status', 'Approved')
    ->sum('cash_out_amount');

    $cash_on_hand = (($event->admin_cash_on_hand ?? 0) - $cash_in) + $cash_out;

    $admin_cash = AdminRemittance::where('event_id', $event->event_id)->first();

    $actual_cash_on_hand =
    (($admin_cash->denom_1000 ?? 0) * 1000) +
    (($admin_cash->denom_500 ?? 0) * 500) +
    (($admin_cash->denom_200 ?? 0) * 200) +
    (($admin_cash->denom_100 ?? 0) * 100) +
    (($admin_cash->denom_50 ?? 0) * 50) +
    (($admin_cash->denom_20 ?? 0) * 20) +
    (($admin_cash->denom_10 ?? 0) * 10) +
    (($admin_cash->denom_5 ?? 0) * 5) +
    (($admin_cash->denom_1 ?? 0) * 1);


    return response()->json([
        'total_collection' => $total_collection,
        'organizer_share' => $total_collection * $event->event_percentage,

        'teller_count' => $teller_count,
        'fight_count' => $fight_count,
        'fight_count_completed' => $fight_count_completed,
        'fight_count_draw' => $fight_count_draw,
        'fight_count_cancelled' => $fight_count_cancelled,
        'bet_count' => $bet_count,
        'bet_count_meron' => $bet_count_meron,
        'bet_count_wala' => $bet_count_wala,
        'voided_bets' =>$voided_bets,
        'claimed_bets' => $claimed_bets,
        'unclaimed_winnings' => $unclaimed_winnings,
        'salaries' => $total_salary,
        'net_for_sharing' => ($total_collection * $event->event_percentage) - $total_salary,
        'system_share' => (($total_collection * $event->event_percentage) - $total_salary) * 0.19,
        'operator_share' => (($total_collection * $event->event_percentage) - $total_salary) * 0.81,
        'revolving_fund' => $event->admin_cash_on_hand,
        'unclaimed' =>$unclaimed_winnings,
        'prizes' => $event->prizes,
        'supposed_cash_on_hand' => $cash_on_hand,
        'actual_cash_on_hand' => $actual_cash_on_hand,
        'difference' => $cash_on_hand - $actual_cash_on_hand,
        'rd' => $event->rd,
        'comission' => $event->event_percentage * 100,
        'short' => ($cash_on_hand - $actual_cash_on_hand) - $event->rd,
    ]);


  }

}
