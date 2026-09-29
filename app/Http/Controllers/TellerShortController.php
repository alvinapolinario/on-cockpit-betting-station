<?php

namespace App\Http\Controllers;

use App\Models\TellerShort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TellerShortController extends Controller
{
  public function list(Request $r)
  {
    return DB::table('teller_shorts')
    ->where('teller_remittance_id', $r->teller_remittance_id)
    ->get();
  }

  public function store(Request $r)
  {
    DB::beginTransaction();

    try {

      $teller_short = TellerShort::create([
        'teller_remittance_id' => $r->teller_remittance_id,
        'short_amount' => $r->short_amount,
        'short_remarks' => $r->short_remarks,
      ]);

      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Teller Short created. " . $teller_short);

      DB::commit();

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
      $teller_short = TellerShort::where('teller_short_id', $r->teller_short_id)->first();

      if (!$teller_short) {
        return 0;
      }

      $teller_short->delete();

      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Teller Short deleted. " . $teller_short);



      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }
}
