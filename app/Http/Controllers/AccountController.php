<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Event;
use App\Models\School;
use App\Models\Teller;
use App\Models\Account;
use App\Models\CashOut;
use App\Models\LogMessage;
use App\Models\EventTeller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{

  public function loginApp(Request $request)
  {

    $event = Event::where('event_status', 'Active')->first();

    $acc = Account::where('username', $request->username)
    ->where('account_type', 'Teller')
    ->first();

    if(empty($acc))
    {
      $this->createLog(-1, "Teller App", "Login attempt by Username: " . $request->userame);
      return response()->json(['message' => 'Invalid credentials! Please contact administrator'], 401);
    }
    else
    {
      if($acc->is_active == 0)
      {
        $this->createLog(-1, "Teller App", "Login attempt by Username: " . $request->userame);
        return response()->json(['message' => 'Login Failed! Account is not activated, please contact administrator!'], 401);
      }
    }

    $account = DB::table('event_tellers_view')
    ->where('event_status', 'Active')
    ->where('username', $request->username)
    ->first();

    if(empty($account))
    {
      $this->createLog(-1, "Teller App", "Login attempt by Username: " . $request->userame);

      return response()->json(['message' => 'Invalid credentials! Please contact administrator'], 401);
    }

    if(!Hash::check($request->password, $account->password))
    {
      $this->createLog($account->account_id, "Teller App", "Failed Login by Teller {$account->teller_name} using device UID: {$request->phone_uid}, reason incorrect password!");

      return response()->json(['message' => 'Incorrect password. Please contact the administrator for assistance.'], 401);
    }


    if(empty($event))
    {
      $this->createLog($account->account_id, "Teller App", "Failed Login by Teller {$account->teller_name} using device UID: {$request->phone_uid}, reason no available event.");

      return response()->json(['message' => 'There is no active event!'], 401);

    }


      if(!empty($account->phone_uid))
      {
          if($request->phone_uid != $account->phone_uid)
          {
            $this->createLog($account->account_id, "Teller App", "Failed Login by Teller {$account->teller_name} using device UID: {$request->phone_uid}, reason not allowed to login to the phone.");

            return response()->json(['message' => 'You are not allowed to login in this phone. Please contact administrator.'], 401);
          }
      }
      else
      {
        $check_phone = Teller::where('phone_uid', $request->phone_uid)
        ->whereNot('teller_id', $account->teller_id)
        ->first();

        if(!empty($check_phone))
        {

          $this->createLog($account->account_id, "Teller App", "Failed Login by Teller {$account->teller_name} using device UID: {$request->phone_uid}, reason logging in to other phone already connected to somebody account");
          return response()->json(['message' => 'This phone is already linked to ' . $check_phone->teller_name], 401);
        }
      }




      if ($account && Hash::check($request->password, $account->password)) {

          $token = Str::random(60);

          // Only the SHA-256 hash is stored; the app keeps the plain token.
          PersonalAccessToken::create([
            'tokenable_id' => $account->event_teller_id,
            'tokenable_type' => 'EventTeller',
            'name' => 'teller-token',
            'token' => hash('sha256', $token),
            'abilities' => json_encode(['*']),
            'expires_at' => now()->addHours(config('betting.token_hours')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $latestMatch = DB::table('matches')
        ->where('event_id', $account->event_id)
        ->orderByDesc('match_number')
        ->first();

        $this->createLog($account->account_id, "Teller App", "Login by Teller {$account->teller_name} using device UID: {$request->phone_uid}");




        return response()->json([
            'token' => $token,
            'teller' => [
                'phone_uid' => $account->phone_uid,
                'event_teller_id' => $account->event_teller_id,
                'teller_id' => $account->teller_id,
                'teller_name' => $account->teller_name,
                'teller_balance' => $account->teller_balance,
                'teller_match_balance' => $account->teller_match_balance,
            ],
            'event' => [
                'event_id' => $account->event_id,
                'event_name' => $account->event_name,
                'event_status' => $account->event_status
            ],
            'match' => $latestMatch ? [
                'match_id' => $latestMatch->match_id,
                'match_number' => $latestMatch->match_number,
                'match_bet_status' => $latestMatch->match_bet_status
            ] : null
        ], 200);

      }
      else
      {
        $this->createLog($account->account_id, "Teller App", "Failed Login by Teller {$account->teller_name} using device UID: {$request->phone_uid}, reason incorrect password!");
        return response()->json(['message' => 'Incorrect password. Please contact the administrator for assistance.'], 401);
      }


  }

  public function loginAppAdmin(Request $request)
  {
    $event = Event::where('event_status', 'Active')->first();


    $account = DB::table('admins_view')->where('username', $request->username)
          ->where('is_active', 1)
          ->first();


    if(empty($account))
    {
      $this->createLog(-1, "Admin App", "Login attempt by Username: " . $request->userame);

      return response()->json(['message' => 'Invalid credentials! Please contact administrator'], 401);
    }


    if(empty($event))
    {
      $this->createLog($account->account_id, "Admin App", "Failed Login by Admin {$account->admin_name}, reason no available event.");
      return response()->json(['message' => 'No active event available!'], 401);
    }


      if ($account && Hash::check($request->password, $account->password)) {

          $token = Str::random(60);

          PersonalAccessToken::create([
            'tokenable_id' => $account->admin_id,
            'tokenable_type' => 'EventAdmin',
            'name' => 'admin-token',
            'token' => hash('sha256', $token),
            'abilities' => json_encode(['*']),
            'expires_at' => now()->addHours(config('betting.token_hours')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $latestMatch = DB::table('matches')
        ->where('event_id', $event->event_id)
        ->orderByDesc('match_number')
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
            'token' => $token,
            'admin' => $account,
            'event' => $event,
            'match' => $latestMatch ? [
                'match_id' => $latestMatch->match_id,
                'match_number' => $latestMatch->match_number,
                'match_bet_status' => $latestMatch->match_bet_status
            ] : null
        ], 200);

      }

      $this->createLog($account->account_id, "Admin App", "Failed Login by Admin {$account->admin_name}, reason incorrect password!");
      return response()->json(['message' => 'Incorrect password. Please contact the administrator for assistance.'], 401);

  }

  public function logoutApp(Request $request)
  {

      $token = $request->bearerToken();

      if ($token) {


         $pas =  PersonalAccessToken::where('token', hash('sha256', $token))->first();

        if (!$pas) {
            return response()->json(['message' => 'Token not found'], 400);
        }

        if($pas->delete())
        {
          return response()->json(['message' => 'Logged out'], 200);
        }

      }

      return response()->json(['message' => 'Token not found'], 400);
  }

  public function loginAccount(Request $r)
  {
    $account = Account::where('username', $r->username)
    ->where('is_active', 1
    )
    ->first();




    if (!empty($account) and Hash::check($r->password, $account->password)) {
      $r->session()->put('account_id', $account->account_id);
      $r->session()->put('account_type', $account->account_type);


      if ($account->account_type == 'Admin') {
        $r->session()->put('welcome_name', $account->username);
        $r->session()->put('name', $account->username);

        $admin = Admin::where('account_id', $account->account_id)->first();

        $r->session()->put('admin', $admin);

        $this->createLog(session()->get('account_id'), "Web App", "Login. " .  $this->getDevice($r));


        return 1;
      } else {
        $this->createLog(session()->get('account_id'), "Web App", "Login Failed, reason account is not Admin. " .  $this->getDevice($r));

        return 0;
      }
    } else {
      $this->createLog(-1, "Web App", "Login Failed. Attempt by Username: " . $r->username . " "  .  $this->getDevice($r));

      return 0;
    }
  }

  public function logout(Request $r)
  {
    $this->createLog(session()->get('account_id'), "Web App", "Logout");

    $r->session()->flush();

    return redirect('/');
  }

  public function updatePassword(Request $request)
  {
    $account = Account::where('account_id', session()->get('account_id'))->first();

    if (!Hash::check($request->old_password, $account->password)) {
        return response()->json(['error' => 'The current password is incorrect.'], 422);
    }

    $account->password = Hash::make($request->new_password);
    $account->update();

    return response()->json(['success' => true]);
  }

  public function changePasswordApp(Request $request)
{


    $request->validate(['new_password' => 'required|string|min:4|max:100']);

    DB::beginTransaction();
    try {

        $teller = Teller::where('teller_id', $request->teller_id)->firstOrFail();
        $account = Account::where('account_id', $teller->account_id)->firstOrFail();


        $account->password = Hash::make($request->new_password);
        $teller->phone_uid = $request->phone_uid;

        $teller->save();
        $account->save();


        $this->createLog($account->account_id, "Teller App", "Change password by Teller #{$teller->teller_id} ({$teller->teller_name}), device UID: {$request->phone_uid}");


        DB::commit();


        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully!'
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();



        return response()->json([
            'success' => false,
            'message' => 'An error occurred while changing the password.',
            'error'   => $e->getMessage()
        ], 500);
    }
}


public function registerDesktopUID(Request $request)
{
  try {
    $teller = Teller::where('teller_id', $request->teller_id)->first();

    if (empty($teller)) {
      return response()->json([
        'success' => false,
        'message' => 'Teller not found'
      ], 404);
    }

    // Register the desktop UID
    $teller->phone_uid = $request->phone_uid;
    $teller->save();

    $this->createLog($teller->account_id, "Desktop App", "Registered desktop UID: {$request->phone_uid} for teller {$teller->teller_name}");

    return response()->json([
      'success' => true,
      'message' => 'Desktop UID registered successfully'
    ], 200);

  } catch (\Exception $e) {
    return response()->json([
      'success' => false,
      'message' => 'Failed to register desktop UID',
      'error' => $e->getMessage()
    ], 500);
  }
}

}
