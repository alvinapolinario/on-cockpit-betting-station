<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
  public function index()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the App Settings page");
    return view('settings', [
      'settings' => AppSetting::get(),
      'qrPayload' => AppSetting::qrPayload(),
    ]);
  }

  public function update(Request $request)
  {
    $data = $request->validate([
      // "|" separates the fields in the setup QR, so neither value may contain it.
      'api_url' => 'required|string|max:180|not_regex:/\|/',
      'app_name' => 'required|string|max:40|not_regex:/\|/',
    ]);

    $settings = AppSetting::put($data['api_url'], $data['app_name']);
    $this->createLog(session()->get('account_id'), "Web App", "Updated app settings. API {$settings['api_url']}, name {$settings['app_name']}");

    return redirect()->route('settings')->with('success', 'Settings saved. Scan the QR with the teller app.');
  }
}
