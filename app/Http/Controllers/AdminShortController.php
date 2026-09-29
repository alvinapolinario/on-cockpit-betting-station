<?php

namespace App\Http\Controllers;

use App\Models\AdminShort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminShortController extends Controller
{
  public function list(Request $r)
  {
    return DB::table('admin_shorts')
    ->where('admin_remittance_id', $r->admin_remittance_id)
    ->get();
  }

  public function store(Request $r)
  {
    DB::beginTransaction();

    try {

      $admin_short = AdminShort::create([
        'admin_remittance_id' => $r->admin_remittance_id,
        'short_amount' => $r->short_amount,
        'short_remarks' => $r->short_remarks,
      ]);

      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Admin Short created. " . $admin_short);

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
      $admin_short = AdminShort::where('admin_short_id', $r->admin_short_id)->first();

      if (!$admin_short) {
        return 0;
      }

      $admin_short->delete();

      $this->createLog(session()->get('account_id'), "Web App", "Remittances - Admin Short deleted. " . $admin_short);



      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }
}
