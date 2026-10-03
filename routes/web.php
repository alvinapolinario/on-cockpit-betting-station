<?php

use Illuminate\Support\Facades\Route;

Route::get('/logout', 'AccountController@logout');
Route::get('/lang/ar', 'PageController@dashboard');
Route::get('/lang/en', 'PageController@dashboard');
Route::get('/tv', 'PageController@tv');
Route::get('/tv-new', 'PageController@tvNew');
Route::get('/tv/get-started', 'MatchController@getStarted');
Route::get('/tv/fight-history', 'MatchController@fightHistory');


Route::middleware(['isLoggedIn'])->group(function () {
  Route::get('/', 'PageController@login')->name('login');
  Route::get('/login', 'PageController@login');
  Route::post('/login', 'AccountController@loginAccount')->middleware('throttle:10,1');

});

Route::middleware(['isAll'])->group(function () {

});

Route::middleware(['isAdmin'])->group(function () {

  Route::get('/dashboard', 'PageController@dashboard')->name('dashboard');

  Route::prefix('accounts')->group(function () {

    Route::prefix('admins')->group(function () {
      Route::get('/', 'AdminController@index')->name('accounts-admins');
      Route::post('/list', 'AdminController@list');
      Route::post('/store', 'AdminController@store');
      Route::post('/update', 'AdminController@update');
      Route::post('/find/{admin_id}', 'AdminController@find');
      Route::delete('/{admin_id}', 'AdminController@delete');
    });

    Route::prefix('tellers')->group(function () {
      Route::get('/', 'TellerController@index')->name('accounts-tellers');
      Route::post('/list', 'TellerController@list');
      Route::post('/store', 'TellerController@store');
      Route::post('/update', 'TellerController@update');
      Route::post('/find/{teller_id}', 'TellerController@find');
      Route::post('/{teller_id}/clear-phone-uid', 'TellerController@clearPhoneUID');
      Route::delete('/{teller_id}', 'TellerController@delete');
    });

  });


  Route::prefix('events')->group(function () {
    Route::get('/', 'EventController@index')->name('events');
    Route::post('/list', 'EventController@list');
    Route::post('/{event}/tellers', 'EventTellerController@listByEvent');
    Route::post('/{event}/update-tellers', 'EventTellerController@updateTellers');
    Route::post('/list', 'EventController@list');
    Route::post('/store', 'EventController@store');
    Route::post('/update', 'EventController@update');
    Route::post('/find/{event_id}', 'EventController@find');
    Route::delete('/{event_id}', 'EventController@delete');
  });

  Route::prefix('matches')->group(function () {
    Route::get('/', 'MatchController@index')->name('matches');
    Route::post('/list', 'MatchController@list');
    Route::get('/get-started', 'MatchController@getStarted');
    Route::get('/new-match', 'MatchController@newMatch');
    Route::post('/{match_id}/toggle-meron', 'MatchController@toggleMeron');
    Route::post('/{match_id}/toggle-wala', 'MatchController@toggleWala');
    Route::post('/{match_id}/{match_bet_status}/update-bet-status', 'MatchController@updateMatchBetStatus');
    Route::post('/{match_id}/{match_status}/{winner}/update-match-status', 'MatchController@updateMatchStatus');
    Route::post('/{match_id}/update-display', 'MatchController@updatedisplay');
    Route::post('/{match_id}/hold', 'MatchController@hold')->whereNumber('match_id');
    Route::get('/bridge-status', 'MatchController@bridgeStatus');

    Route::post('/find/{match_id}', 'MatchController@find');
    Route::delete('/{match_id}', 'MatchController@delete');
  });

  Route::prefix('reports')->group(function () {
    Route::get('/', 'EventController@reports')->name('reports');
    Route::post('/report/{event_id}', 'MatchController@report');

  });

  Route::prefix('cash-transactions')->group(function () {
    Route::get('/', 'PageController@cashTransactions')->name('cash-transactions');
    Route::get('/tellers/load', 'EventTellerController@getTellers');
    Route::post('/tellers/list', 'EventTellerController@listTellersByEvent');
    Route::post('/cash-in', 'CashInController@storeByAdmin');
    Route::post('/cash-in/approve', 'CashInController@approveCashIn');
    Route::post('/cash-out/approve', 'CashOutController@approveCashOut');
    Route::post('/cash-in/decline', 'CashInController@declineCashIn');
    Route::post('/cash-out/decline', 'CashOutController@declineCashOut');

  });

  Route::prefix('teller-transactions')->group(function () {
    Route::get('/', 'PageController@tellerTransactions')->name('teller-transactions');
    Route::post('/get-data', 'EventController@getData');
    Route::post('/history', 'BetController@history');
  });


  // Teller account ledger (admins only)
  // Read-only audit of closed events (GET only).
  Route::prefix('event-audit')->group(function () {
    Route::get('/', 'EventAuditController@index')->name('event-audit');
    Route::get('/{event_id}', 'EventAuditController@show')->whereNumber('event_id')->name('event-audit.show');
    Route::get('/{event_id}/verify', 'EventAuditController@verify')->whereNumber('event_id')->name('event-audit.verify');
  });

  Route::prefix('teller-ledger')->group(function () {
    Route::get('/', 'TellerLedgerController@index')->name('teller-ledger');
    Route::get('/all', 'TellerLedgerController@all')->name('teller-ledger.all');
    Route::get('/{event_teller_id}/print', 'TellerLedgerController@print')->whereNumber('event_teller_id')->name('teller-ledger.print');
  });

  Route::prefix('logs')->group(function () {
    Route::get('/', 'PageController@logs')->name('logs');
    Route::post('/list', 'TransactionController@fetchLogs');
  });

  Route::prefix('bets')->group(function () {
    Route::get('/', 'PageController@bets')->name('bets');
    Route::post('/list/{event_id}', 'BetController@listAll');
  });

  Route::get('/unclaimed-password', 'PageController@unclaimedPassword')->name('unclaimed.password');

  Route::post('/unclaimed-password', 'PageController@checkUnclaimedPassword')->name('unclaimed.password.check');

   Route::prefix('unclaimed')->group(function () {
    Route::get('/', 'PageController@unclaimed')->name('unclaimed')->middleware('unclaimed.password');
    Route::post('/list/{event_id}', 'BetController@listAllUnclaimed');
  });

  Route::prefix('salaries')->group(function () {
    Route::get('/', 'PageController@salaries')->name('salaries');
    Route::post('/list', 'SalaryController@list');
    Route::post('/store/{event_id}', 'SalaryController@store');
    Route::post('/update', 'SalaryController@update');
    Route::post('/find/{salary_id}', 'SalaryController@find');
    Route::delete('/{salary_id}', 'SalaryController@delete');
  });

  Route::prefix('remittances')->group(function () {
    Route::get('/', 'PageController@remittances')->name('remittances');
    Route::post('/tellers/list', 'TellerRemittanceController@list');
    Route::post('/admins/list', 'AdminRemittanceController@list');
    Route::post('/generate/{event_id}', 'TellerRemittanceController@generate');
    Route::post('/teller/update', 'TellerRemittanceController@update');
    Route::post('/admin/update', 'AdminRemittanceController@update');
    Route::post('/teller/find/{teller_remittance_id}', 'TellerRemittanceController@find');
    Route::post('/admin/find/{admin_remittance_id}', 'AdminRemittanceController@find');
    Route::delete('/{teller_remittance_id}', 'TellerRemittanceController@delete');


    Route::get('/teller-shorts/list/{teller_remittance_id}', 'TellerShortController@list');
    Route::post('/teller-shorts/store', 'TellerShortController@store');
    Route::delete('/teller-shorts/delete/{teller_short_id}', 'TellerShortController@delete');

    Route::get('/admin-shorts/list/{admin_remittance_id}', 'AdminShortController@list');
    Route::post('/admin-shorts/store', 'AdminShortController@store');
    Route::delete('/admin-shorts/delete/{admin_short_id}', 'AdminShortController@delete');
  });

  Route::prefix('closing-reports')->group(function () {
    Route::get('/', 'ClosingReportController@index')->name('closing-reports');
    Route::get('/preview/{event_id}', 'ClosingReportController@preview')->whereNumber('event_id')->name('closing-reports.preview');
    Route::post('/seal/{event_id}', 'ClosingReportController@seal')->whereNumber('event_id')->name('closing-reports.seal');
    Route::get('/{event_closing_id}', 'ClosingReportController@show')->whereNumber('event_closing_id')->name('closing-reports.show');
    Route::get('/{event_closing_id}/download', 'ClosingReportController@download')->whereNumber('event_closing_id')->name('closing-reports.download');
    Route::post('/{event_closing_id}/acknowledge', 'ClosingReportController@acknowledge')->whereNumber('event_closing_id')->name('closing-reports.acknowledge');
  });

  Route::prefix('backups')->group(function () {
    Route::get('/', 'BackupController@index')->name('backups');
    Route::post('/', 'BackupController@store')->name('backups.store');
    Route::post('/{file}/verify', 'BackupController@verify')->where('file', '[A-Za-z0-9_.-]+\\.bak\\.enc')->name('backups.verify');
    // TESTING ONLY: raw .sql dumps placed in storage/app/backups/import (disabled in production)
    Route::post('/import/{file}/check', 'BackupController@checkImport')->where('file', '[A-Za-z0-9_.-]+\\.sql(\\.gz)?')->name('backups.import.check');
    Route::post('/import/{file}/restore', 'BackupController@restoreImport')->where('file', '[A-Za-z0-9_.-]+\\.sql(\\.gz)?')->name('backups.import.restore');
    Route::post('/{file}/restore', 'BackupController@restore')->where('file', '[A-Za-z0-9_.-]+\\.bak\\.enc')->name('backups.restore');
  });

  // Reset page removed: it wiped event data (and its "backup" never worked). Use System → Backups instead.


});
