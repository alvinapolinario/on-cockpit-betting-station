<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Models\AdminRemittance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminRemittanceController extends Controller
{

  public function find(Request $r)
  {
    return DB::table('admin_remittances_view')
    ->where('admin_remittance_id', $r->admin_remittance_id)
    ->first();
  }


  public function list(Request $r)
  {

    $event = Event::where('event_id', $r->event_id)->first();

    $cash_in = DB::table('cash_ins_view')
    ->where('event_id', $r->event_id)
    ->where('cash_in_status', 'Approved')
    ->sum('cash_in_amount');

    $cash_out = DB::table('cash_outs_view')
    ->where('event_id', $r->event_id)
    ->where('cash_out_status', 'Approved')
    ->sum('cash_out_amount');

    $cash_on_hand = (($event->admin_cash_on_hand ?? 0) - $cash_in) + $cash_out;


    $res =  DB::table('admin_remittances_view')
    ->where('event_id', $r->event_id)
    ->get();


    foreach($res as $re)
    {
      $re->cash_on_hand = $cash_on_hand;
    }

    return $res;

  }

  public function update(Request $r)
  {
    Log::info($r);
    DB::beginTransaction();

    try {
      $remittance = AdminRemittance::where('admin_remittance_id', $r->admin_uid)->firstOrFail();


      $remittance->denom_1000 = $r->admin_denom_1000;
      $remittance->denom_500 = $r->admin_denom_500;
      $remittance->denom_200 = $r->admin_denom_200;
      $remittance->denom_100 = $r->admin_denom_100;
      $remittance->denom_50 = $r->admin_denom_50;
      $remittance->denom_20 = $r->admin_denom_20;
      $remittance->denom_10 = $r->admin_denom_10;
      $remittance->denom_5 = $r->admin_denom_5;
      $remittance->denom_1 = $r->admin_denom_1;
      $remittance->save();

      DB::commit();


      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Remittance updated. " . $remittance);

      return 1;
    } catch (\Exception $e) {

      DB::rollBack();
      return 0;
    }
  }

}
