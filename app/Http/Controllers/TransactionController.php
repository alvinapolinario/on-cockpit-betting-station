<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
  public function fetchLogs(Request $request)
{
    $logs = DB::table('transactions')
        ->selectRaw("*, get_transaction_account_name(account_id, transaction_type) as account_name")
        ->orderByDesc('transaction_datetime')
        ->get();

    return response()->json([
        'logs' => $logs
    ]);
}

}
