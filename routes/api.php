<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (mobile teller app, admin app, desktop app)
|--------------------------------------------------------------------------
|
| Every route except the two logins requires "Authorization: Bearer <token>".
| account.token:teller / account.token:admin restrict a route to that role,
| and the caller's identity is taken from the token (see CheckAccountToken).
|
*/

// Logins: limited to 10 attempts per minute per IP address.
Route::middleware('throttle:10,1')->group(function () {
  Route::post('/login-app', 'AccountController@loginApp');
  Route::post('/login-app-admin', 'AccountController@loginAppAdmin');
});

Route::middleware('account.token:any')->post('/logout', 'AccountController@logoutApp');

// Matching system on the same server (signed with the shared bridge keys, see config/bridge.php).
Route::middleware('throttle:240,1')->prefix('bridge/matching')->group(function () {
  Route::post('/ping', 'BridgeController@ping');
  Route::post('/call', 'BridgeController@call');
  Route::post('/recall', 'BridgeController@recall');
});

// Teller app / desktop app
Route::middleware('account.token:teller')->group(function () {
  Route::post('/change-password', 'AccountController@changePasswordApp');
  Route::post('/register-desktop-uid', 'AccountController@registerDesktopUID');
  Route::post('/create-bet', 'BetController@store');
  Route::post('/teller-data', 'EventTellerController@getData');
  Route::post('/reprint-last-receipt', 'BetController@reprint');
  Route::post('/reprint-teller', 'BetController@reprintTeller');
  Route::post('/latest-match', 'MatchController@getLatestMatch');
  Route::post('/bet-history', 'BetController@betHistory');
  Route::post('/bet-history-ongoing', 'BetController@betHistoryOngoing');
  Route::post('/cash-in', 'CashInController@storeByTeller');
  Route::post('/cash-in/history', 'CashInController@cashInHistory');
  Route::post('/cash-out', 'CashOutController@storeByTeller');
  Route::post('/cash-out/history', 'CashOutController@cashOutHistory');
  Route::post('/claim-winning', 'ClaimController@claimWinning');
  Route::post('/claim-winning-reprint', 'ClaimController@claimWinningReprint');
  Route::post('/claimed-receipts', 'ClaimController@cashOutHistory');
  Route::post('/unclaimed-receipts', 'BetController@unclaimedReceipts');
  Route::post('/void-bet', 'BetController@voidBet');
  Route::post('/voided-bets', 'BetController@voidedBets');
});

// Admin app
Route::middleware('account.token:admin')->group(function () {
  Route::post('/cash-in-admin', 'CashInController@storeByAdminViaApp');
  Route::post('/cash-in-admin/history', 'CashInController@cashInHistoryAdmin');
  Route::post('/cash-in-admin/approval', 'CashInController@approvalViaApp');

  Route::post('/admin-data', 'AdminController@getData');

  Route::post('/cash-out-admin', 'CashOutController@storeByAdminViaApp');
  Route::post('/cash-out-admin/history', 'CashOutController@cashOutHistoryAdmin');
  Route::post('/cash-out-admin/approval', 'CashOutController@approvalViaApp');

  Route::post('/event-tellers-admin', 'EventTellerController@listTellersAdmin');
  Route::post('/reprint-admin', 'BetController@reprintAdmin');

  Route::post('/matches-admin/list', 'MatchController@listApp');
  Route::post('/matches-admin/get-started', 'MatchController@getStartedApp');
  Route::post('/matches-admin/latest', 'MatchController@getLatestMatch');
  Route::post('/matches-admin/toggle-meron', 'MatchController@toggleMeron');
  Route::post('/matches-admin/toggle-wala', 'MatchController@toggleWala');
  Route::post('/matches-admin/update-bet-status', 'MatchController@updateMatchBetStatus');
  Route::post('/matches-admin/update-match-status', 'MatchController@updateMatchStatus');
  Route::post('/matches-admin/update-display', 'MatchController@updatedisplay');

  Route::post('/events-admin/list', 'EventController@list');
  Route::post('/events-admin/find', 'EventController@find');
  Route::post('/events-admin/store', 'EventController@store');
  Route::post('/events-admin/update', 'EventController@update');
  Route::post('/events-admin/delete', 'EventController@delete');
  Route::post('/events-admin/tellers', 'EventTellerController@listByEventApp');
  Route::post('/events-admin/update-tellers', 'EventTellerController@updateTellersApp');
});
