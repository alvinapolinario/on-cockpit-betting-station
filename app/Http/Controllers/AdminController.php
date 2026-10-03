<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
  public function index()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Admins Account page");

    return view('accounts.admins');
  }

  public function find(Request $r)
  {
    return DB::table('admins_view')
    ->where('admin_id', $r->admin_id)->first();
  }

  public function getData(Request $r)
  {
    $event = Event::where('event_status', 'Active')->first();

    $account = DB::table('admins_view')->where('admin_id', $r->admin_id)
          ->where('is_active', 1)
          ->first();

    $latestMatch = DB::table('matches')
    ->where('event_id', $event->event_id)
    ->orderByDesc('match_id')
    ->first();

    $cash_out = DB::table('cash_outs_view')
    ->where('event_id', $event->event_id)
    ->where('cash_out_status', 'Approved')
    ->sum('cash_out_amount');

    $cash_in = DB::table('cash_ins_view')
    ->where('event_id', $event->event_id)
    ->where('cash_in_status', 'Approved')
    ->sum('cash_in_amount');

    return response()->json([
      'cash_out' => $cash_out,
      'cash_in' => $cash_in,
      'token' => '',
      'admin' => $account,
      'event' => $event,
      'match' => $latestMatch ? [
          'match_id' => $latestMatch->match_id,
          'match_number' => $latestMatch->match_number,
          'match_bet_status' => $latestMatch->match_bet_status
      ] : null
    ], 200);


  }


  public function list()
  {
    return DB::table('admins_view')
    ->get();
  }

  public function store(Request $r)
  {
    DB::beginTransaction();

    try {

      $is_exist = Account::where('username', $r->username)->first();

      if(!empty($is_exist))
      {
        return -1;
      }

      $account = Account::create([
        'username' => $r->username,
        'password' => Hash::make($r->password),
        'account_type' => 'Admin',
        'is_active' => 1,
        'account_date_created' => date('Y-m-d H:i:s'),
      ]);


      $admin = Admin::create([
        'account_id' => $account->account_id,
        'admin_name' => $r->admin_name,
      ]);

      $this->createLog(session()->get('account_id'), "Web App", "Admin - Admin created. " . $admin . " " . $account);

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
      $admin = Admin::where('admin_id', $r->uid)->firstOrFail();
      $account = Account::where('account_id', $admin->account_id)->firstOrFail();


      if (!empty($r->_password)) {
        $account->password = Hash::make($r->_password);
      }


      $account->is_active = $r->_is_active;
      $account->save();


      $admin->admin_name = $r->_admin_name;
      $admin->save();

      $this->createLog(session()->get('account_id'), "Web App", "Admin - Admin updated. " . $admin . " " . $account);

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
      $admin = Admin::where('admin_id', $r->admin_id)->first();
      $account = Account::where('account_id', $admin->account_id)->first();

      if (!$admin || !$account) {
        return 0;
      }

      $admin->delete();
      $account->delete();

      $this->createLog(session()->get('account_id'), "Web App", "Admin - Admin deleted. " . $admin . " " . $account);

      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }

}
