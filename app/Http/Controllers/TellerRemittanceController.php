<?php

namespace App\Http\Controllers;

use App\Models\AdminRemittance;
use Illuminate\Http\Request;
use App\Models\TellerRemittance;
use Illuminate\Support\Facades\DB;

class TellerRemittanceController extends Controller
{
  public function find(Request $r)
  {
    return DB::table('teller_remittances_view')
    ->where('teller_remittance_id', $r->teller_remittance_id)
    ->first();
  }

  public function list(Request $r)
  {
    return DB::table('teller_remittances_view')
    ->where('event_id', $r->event_id)
    ->get();
  }

  public function generate(Request $r)
  {
    DB::beginTransaction();

    try {

    $event_tellers = DB::table('event_tellers_view')
    ->where('event_id', $r->event_id)
    ->get();

    foreach($event_tellers as $event_teller)
    {
      $exists = TellerRemittance::where('event_teller_id', $event_teller->event_teller_id)->exists();

      if (!$exists) {
        $remittances = TellerRemittance::create([
          'event_teller_id' => $event_teller->event_teller_id,
          'denom_1000' => 0,
          'denom_500' => 0,
          'denom_200' => 0,
          'denom_100' => 0,
          'denom_50' => 0,
          'denom_20' => 0,
          'denom_10' => 0,
          'denom_5' => 0,
          'denom_1' => 0,
        ]);

        $this->createLog(session()->get('account_id'), "Web App", "Remittances - Empty Remittance created. " . $remittances);
      }
    }


    $tellers =  DB::table('event_tellers_view')
    ->where('event_id', $r->event_id)
    ->distinct()
    ->pluck('event_teller_id')
    ->toArray();

    TellerRemittance::whereNotIn('event_teller_id', $tellers)
    ->delete();

    $exists = AdminRemittance::where('event_id', $r->event_id)->exists();

    if(!$exists)
    {
      $remittances = AdminRemittance::create([
        'event_id' => $r->event_id,
        'denom_1000' => 0,
        'denom_500' => 0,
        'denom_200' => 0,
        'denom_100' => 0,
        'denom_50' => 0,
        'denom_20' => 0,
        'denom_10' => 0,
        'denom_5' => 0,
        'denom_1' => 0,
      ]);
      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Empty Remittance created." . $remittances);
    }

    DB::commit();

    return 1;

  } catch (\Exception $e) {
    DB::rollBack();
    return 0;
  }
  }



  public function update(Request $r)
  {
    DB::beginTransaction();

    try {
      $remittance = TellerRemittance::where('teller_remittance_id', $r->uid)->firstOrFail();


      $remittance->denom_1000 = $r->_denom_1000;
      $remittance->denom_500 = $r->_denom_500;
      $remittance->denom_200 = $r->_denom_200;
      $remittance->denom_100 = $r->_denom_100;
      $remittance->denom_50 = $r->_denom_50;
      $remittance->denom_20 = $r->_denom_20;
      $remittance->denom_10 = $r->_denom_10;
      $remittance->denom_5 = $r->_denom_5;
      $remittance->denom_1 = $r->_denom_1;
      $remittance->save();

      DB::commit();


      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Remittance updated. " . $remittance);

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
      $remittance = TellerRemittance::where('teller_remittance_id', $r->teller_remittance_id)->first();

      if (!$remittance) {
        return 0;
      }

      $remittance->delete();

      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Remittance deleted. " . $remittance);

      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }

}
