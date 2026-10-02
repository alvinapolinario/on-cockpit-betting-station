<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Fight;
use App\Models\School;
use App\Models\Teller;
use App\Models\Account;
use App\Models\Setting;
use App\Models\Application;
use App\Models\EventTeller;
use App\Events\MatchUpdated;
use Illuminate\Http\Request;
use App\Models\DivisionOffice;
use App\Models\RegionalOffice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\TellerBalanceUpdated;
use Illuminate\Support\Facades\Hash;

class PageController extends Controller
{

  public function login()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('login')->with('pageConfigs', $pageConfigs);
  }

  public function dashboard()
  {
    $event = Event::where('event_status', 'Active')
    ->first();

    // Demo only (DASHBOARD_SHOW_LAST_EVENT): show the latest closed event when none is active.
    if (empty($event) && config('betting.dashboard_show_last_event')) {
      $event = Event::where('event_status', 'Closed')->orderByDesc('event_date')->orderByDesc('event_id')->first();
    }

    if (empty($event)) {
      return view('dashboard')
        ->with('bets', collect())
        ->with('claimed', 0)
        ->with('unclaimed', 0)
        ->with('current_match', null)
        ->with('total_bets', 0)
        ->with('gross_bets', 0)
        ->with('voided_bets', 0)
        ->with('voided_count', 0)
        ->with('matches', collect())
        ->with('categories', [])
        ->with('meronData', [])
        ->with('walaData', [])
        ->with('event_percentage', 0)
        ->with('event', null);
    }

    $matches = Fight::where('event_id', $event->event_id)
    ->orderBy('match_number', 'desc')
    ->get();

    $current_match = Fight::where('event_id', $event->event_id)
    ->where('match_status', 'Ongoing')
    ->first();

    $bets = DB::table('bets_view')
    ->selectRaw("*, get_teller_name(event_teller_id) as teller_name, get_teller_name(bet_payout_by) as payout_name")
    ->where('event_id', $event->event_id)
    ->get();

    $claimed = $bets->where('event_id', $event->event_id)
    ->whereNotNull("bet_payout_by")
    ->sum('bet_payout_amount');

    $unclaimed = $bets->where('event_id', $event->event_id)
    ->whereNull("bet_payout_by")
    ->sum('bet_payout_amount');

    $total_bets = $bets->where('bet_status', 1)
    ->where('match_status', 'Completed')
    ->sum('bet_amount');

    // Gross = every bet placed in the event, including voided ones. Counted by fight, exactly
    // like the closing report (bets_view also drops bets whose teller assignment was removed).
    $eventBets = DB::table('bets')->join('matches', 'matches.match_id', '=', 'bets.match_id')
      ->where('matches.event_id', $event->event_id);
    $gross_bets = (clone $eventBets)->sum('bets.bet_amount');
    $voidedBets = (clone $eventBets)->where('bets.bet_status', 2)->selectRaw('COUNT(*) AS n, COALESCE(SUM(bets.bet_amount), 0) AS amount')->first();

    $betStats = $bets->groupBy('match_number')->map(function ($group) {
      $first = $group->first();
      return [
          'Meron' => $first->meron_total_bet ?? 0,
          'Wala'  => $first->wala_total_bet ?? 0,
      ];
    })->sortKeysUsing(function ($a, $b) {
      return (int) $a <=> (int) $b;
    });

    $categories = $betStats->keys()->toArray();
    $meronData = $betStats->pluck('Meron')->toArray();
    $walaData  = $betStats->pluck('Wala')->toArray();


    return view('dashboard')
    ->with('bets', $bets)
    ->with('claimed', $claimed)
    ->with('unclaimed', $unclaimed)
    ->with('current_match', $current_match)
    ->with('total_bets', $total_bets)
    ->with('gross_bets', $gross_bets)
    ->with('voided_bets', $voidedBets->amount)
    ->with('voided_count', $voidedBets->n)
    ->with('matches', $matches)
    ->with('categories', $categories)
    ->with('meronData', $meronData)
    ->with('walaData', $walaData)
    ->with('event_percentage', $event->event_percentage)
    ->with('event', $event);
  }


  public function changePassword()
  {
    return view('change-password');
  }


  public function cashTransactions()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Cash Transactions page");
    return view('cash-transactions');
  }

  public function tellerTransactions()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Teller Transactions page");
    return view('teller-transactions');
  }

  public function tv()
  {
    $event = Event::where('event_status', 'Active')->first();

    return view('tv')
    ->with('event', $event);
  }

  public function tvNew()
  {
    $event = Event::where('event_status', 'Active')->first();

    return view('tv-new')
    ->with('event', $event);
  }


  public function bets()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Bets page");
    return view('bets');
  }

  public function unclaimed()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Unclaimed page");
    return view('unclaimed');
  }

  public function unclaimedPassword()
  {
    return view('unclaimed-password');
  }

  public function checkUnclaimedPassword(Request $request)
  {
    // Re-enter your own admin password (no shared hardcoded password).
    $account = \App\Models\Account::where('account_id', session()->get('account_id'))->first();
    if ($account && is_string($request->password) && \Illuminate\Support\Facades\Hash::check($request->password, $account->password)) {
      session(['unclaimed_authenticated' => true]);
      return redirect()->route('unclaimed');
    }

    return back()->with('error', 'Invalid password');
  }

  public function logs()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Logs page");
    return view('logs');
  }

  public function salaries()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Salaries page");
    return view('salaries');
  }

  public function remittances()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Remittances page");
    return view('remittances');
  }

}
