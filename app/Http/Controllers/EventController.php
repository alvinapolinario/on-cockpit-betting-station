<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Fight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class EventController extends Controller
{
  public function index()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Events page");
    return view('events');
  }

  public function reports()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Reports page");
    return view('reports');
  }

  public function find(Request $r)
  {
    return Event::where('event_id', $r->event_id)->first();
  }

  public function list()
  {
    return Event::all();
  }

  public function store(Request $r)
  {
    if (!is_numeric($r->event_percentage) || $r->event_percentage < 0 || $r->event_percentage >= 1) {
      return response()->json(['message' => 'Commission must be a decimal between 0 and 1 (e.g. 0.07 for 7%).'], 422);
    }
    if (!in_array($r->event_status, ['Active', 'Completed'], true)) {
      return response()->json(['message' => 'Event status must be Active or Completed.'], 422);
    }
    if ($r->event_status === 'Active' && Event::where('event_status', 'Active')->exists()) {
      return response()->json(['message' => 'Another event is already Active. Complete or close it first.'], 422);
    }
    DB::beginTransaction();

    try {
       $event=  Event::create([
        'event_name' => $r->event_name,
        'event_description' => $r->event_description,
        'event_percentage' => $r->event_percentage,
        'event_date' => $r->event_date,
        'event_status' => $r->event_status,
        'admin_cash_on_hand' => $r->admin_cash_on_hand,
        'teller_initial_cash_on_hand' => $r->teller_initial_cash_on_hand,
        'prizes' => $r->prizes,
        'rd' => $r->rd,
      ]);

      $this->createLog(session()->get('account_id'), "Web App", "Event - Event created. " . $event);

      DB::commit();


      return 1;

    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }

  public function getData(Request $r)
  {


    DB::beginTransaction();

    try {

      $event = Event::where('event_id', $r->event_id)
      ->first();

      $total_collections = DB::table('bets_view')
      ->where('event_id', $event->event_id)
      ->where('bet_status', 1)
      ->where('match_status', 'Completed')
      ->sum('bet_amount');

      $total_fights = Fight::where('event_id', $event->event_id)
      ->count();

      $cash_in = DB::table('cash_ins_view')
      ->where('event_id', $event->event_id)
      ->where('cash_in_status', 'Approved')
      ->sum('cash_in_amount');

      $cash_out = DB::table('cash_outs_view')
      ->where('event_id', $event->event_id)
      ->where('cash_out_status', 'Approved')
      ->sum('cash_out_amount');

      return response()->json([
          'total_collections' => $total_collections,
          'total_fights' => $total_fights,
          'cash_on_hand' => ($event->admin_cash_on_hand - $cash_in) + $cash_out,
      ]);


      DB::commit();


    } catch (\Exception $e) {
      DB::rollBack();

    }

  }

  public function update(Request $r)
  {
    if (!is_numeric($r->_event_percentage) || $r->_event_percentage < 0 || $r->_event_percentage >= 1) {
      return response()->json(['message' => 'Commission must be a decimal between 0 and 1 (e.g. 0.07 for 7%).'], 422);
    }
    if (!in_array($r->_event_status, ['Active', 'Completed'], true)) {
      return response()->json(['message' => 'Event status must be Active or Completed.'], 422);
    }
    if ($r->_event_status === 'Active' && Event::where('event_status', 'Active')->where('event_id', '<>', $r->uid)->exists()) {
      return response()->json(['message' => 'Another event is already Active. Complete or close it first.'], 422);
    }
    DB::beginTransaction();

    try {
      $event = Event::where('event_id', $r->uid)->firstOrFail();


      $event->event_name = $r->_event_name;
      $event->event_description = $r->_event_description;
      $event->event_percentage = $r->_event_percentage;
      $event->event_date = $r->_event_date;
      $event->event_status = $r->_event_status;
      $event->admin_cash_on_hand = $r->_admin_cash_on_hand;
      $event->teller_initial_cash_on_hand = $r->_teller_initial_cash_on_hand;
      $event->prizes = $r->_prizes;
      $event->rd = $r->_rd;
      $event->save();

      DB::commit();


      $this->createLog(session()->get('account_id'), "Web App", "Event - Event updated. " . $event);

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }


  public function delete(Request $r)
  {
    DB::beginTransaction();

    try {
      $event = Event::where('event_id', $r->event_id)->first();

      if (!$event) {
        return 0;
      }

      $event->delete();

      $this->createLog(session()->get('account_id'), "Web App", "Event - Event deleted. " . $event);



      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }
}
