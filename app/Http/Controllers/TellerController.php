<?php

namespace App\Http\Controllers;

use App\Models\Teller;
use App\Models\Account;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class TellerController extends Controller
{
  public function index()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Tellers Account page");
    return view('accounts.tellers');
  }

  public function find(Request $r)
  {
    return DB::table('tellers_view')
    ->where('teller_id', $r->teller_id)->first();
  }

  public function list()
  {
    return DB::table('tellers_view')
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
        'account_type' => 'Teller',
        'is_active' => 1,
        'account_date_created' => date('Y-m-d H:i:s'),
      ]);


      $teller = Teller::create([
        'account_id' => $account->account_id,
        'teller_name' => $r->teller_name,
        'contact_number' => $r->contact_number,
      ]);

      $this->createLog(session()->get('account_id'), "Web App", "Teller - Teller created. " . $teller . " " . $account);


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
      $teller = Teller::where('teller_id', $r->uid)->firstOrFail();
      $account = Account::where('account_id', $teller->account_id)->firstOrFail();


      if (!empty($r->_password)) {
        $account->password = Hash::make($r->_password);
      }


      $account->is_active = $r->_is_active;
      $account->save();


      $teller->teller_name = $r->_teller_name;
      $teller->contact_number = $r->_contact_number;
      $teller->save();

      $this->createLog(session()->get('account_id'), "Web App", "Teller - Teller updated. " . $teller . " " . $account);

      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }

  public function clearPhoneUID(Request $r)
  {
    DB::beginTransaction();

    try {
      $teller = Teller::where('teller_id', $r->teller_id)->firstOrFail();

      $teller->phone_uid = '';

      $teller->save();

      $this->createLog(session()->get('account_id'), "Web App", "Teller - Teller phone uid cleared. " . $teller);

      DB::commit();




      return 1;
    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }




  // Returns the teller's login QR content, creating it on first use.
  public function loginQr(Request $r)
  {
    $teller = Teller::where('teller_id', $r->teller_id)->firstOrFail();

    if (empty($teller->qr_token)) {
      $this->issueLoginQr($teller);
      $this->createLog(session()->get('account_id'), "Web App", "Teller - Login QR created for Teller #{$teller->teller_id} ({$teller->teller_name})");
    }

    return $this->loginQrResponse($teller);
  }

  // Replaces the teller's login QR; the old QR stops working immediately.
  public function regenerateLoginQr(Request $r)
  {
    $teller = Teller::where('teller_id', $r->teller_id)->firstOrFail();

    $this->issueLoginQr($teller);
    $this->createLog(session()->get('account_id'), "Web App", "Teller - Login QR regenerated for Teller #{$teller->teller_id} ({$teller->teller_name})");

    return $this->loginQrResponse($teller);
  }

  private function issueLoginQr(Teller $teller)
  {
    $code = 'SBQR1:' . Str::random(48);

    $teller->qr_token_hash = hash('sha256', $code);
    $teller->qr_token = Crypt::encryptString($code);
    $teller->qr_generated_at = date('Y-m-d H:i:s');
    $teller->save();
  }

  private function loginQrResponse(Teller $teller)
  {
    $username = Account::where('account_id', $teller->account_id)->value('username');

    return response()->json([
      'teller_id' => $teller->teller_id,
      'teller_name' => $teller->teller_name,
      'username' => $username,
      'qr_code' => Crypt::decryptString($teller->qr_token),
      'qr_generated_at' => $teller->qr_generated_at,
    ]);
  }

  public function delete(Request $r)
  {
    DB::beginTransaction();

    try {
      $teller = Teller::where('teller_id', $r->teller_id)->first();
      $account = Account::where('account_id', $teller->account_id)->first();

      if (!$teller || !$account) {
        return 0;
      }

      $teller->delete();
      $account->delete();



      $this->createLog(session()->get('account_id'), "Web App", "Teller - Teller deleted. " . $teller . " " . $account);

      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }
}
