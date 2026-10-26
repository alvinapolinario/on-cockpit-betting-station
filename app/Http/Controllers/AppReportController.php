<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Crash reports from the teller app. The system runs offline, so instead of an online crash
 * service the app saves each crash on the phone and sends it here after the next login.
 * Reports land in the Laravel log (storage/logs) under "Teller app crash".
 */
class AppReportController extends Controller
{
  public function crash(Request $request)
  {
    $request->validate(['report' => 'required|string|max:20000']);

    Log::error('Teller app crash', [
      'event_teller_id' => $request->event_teller_id,
      'device' => $request->header('X-Device-Id'),
      'app_version' => $request->header('X-App-Version'),
      'report' => $request->report,
    ]);
    $this->createLog($request->attributes->get('account_id', -1), "Teller App",
      "Crash report received from app version " . $request->header('X-App-Version') . "; details in the server log.");

    return response()->json(['success' => true]);
  }
}
