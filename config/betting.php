<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Betting limits and API session rules
  |--------------------------------------------------------------------------
  */

  // Accepted range for a single bet (pesos). The teller app reads these at login, so this
  // is the one place to change them. Defaults are the house rule the app used to hardcode.
  'min_bet' => (float) env('BET_MIN', 100),
  'max_bet' => (float) env('BET_MAX', 50000),

  // Accepted range for a single cash-in / cash-out (pesos).
  'max_cash_movement' => (float) env('CASH_MAX', 1000000),

  // Mobile/desktop app login tokens expire after this many hours.
  'token_hours' => (int) env('API_TOKEN_HOURS', 18),

  // Oldest teller app build (versionCode) allowed to log in; 0 allows any. Raise it after
  // installing a new APK on every phone, so outdated phones are told to update.
  'min_app_version' => (int) env('APP_MIN_VERSION', 0),

  // Results may only be declared after betting on the fight is closed.
  'require_closed_before_result' => (bool) env('REQUIRE_CLOSED_BEFORE_RESULT', true),

  // Random digits in a bet receipt code (cryptographically random).
  'receipt_random_digits' => (int) env('RECEIPT_RANDOM_DIGITS', 6),

  // DEMO ONLY: when no event is active, show the most recent closed event on
  // the dashboard (read-only figures). Keep false at the arena.
  'dashboard_show_last_event' => (bool) env('DASHBOARD_SHOW_LAST_EVENT', false),
];
