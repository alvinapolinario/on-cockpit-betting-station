<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Betting limits and API session rules
  |--------------------------------------------------------------------------
  */

  // Accepted range for a single bet (pesos).
  'min_bet' => (float) env('BET_MIN', 1),
  'max_bet' => (float) env('BET_MAX', 1000000),

  // Accepted range for a single cash-in / cash-out (pesos).
  'max_cash_movement' => (float) env('CASH_MAX', 1000000),

  // Mobile/desktop app login tokens expire after this many hours.
  'token_hours' => (int) env('API_TOKEN_HOURS', 18),

  // Results may only be declared after betting on the fight is closed.
  'require_closed_before_result' => (bool) env('REQUIRE_CLOSED_BEFORE_RESULT', true),

  // Random digits in a bet receipt code (cryptographically random).
  'receipt_random_digits' => (int) env('RECEIPT_RANDOM_DIGITS', 6),
];
