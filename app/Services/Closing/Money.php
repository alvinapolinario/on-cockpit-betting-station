<?php

namespace App\Services\Closing;

/**
 * Exact money arithmetic in integer centavos (no floating point).
 */
final class Money
{
  /** "1234.5" / 1234.50 / null -> 123450 */
  public static function cents($value): int
  {
    if ($value === null || $value === '') return 0;
    return (int) bcmul((string) $value, '100', 0);
  }

  /** 123450 -> "1234.50", -5 -> "-0.05" */
  public static function fmt(int $cents): string
  {
    $sign = $cents < 0 ? '-' : '';
    $abs = abs($cents);
    return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
  }

  /** Commission: cents x rate (e.g. "0.078"), rounded half-up to the centavo. */
  public static function percentOf(int $cents, string $rate): int
  {
    return (int) round((float) bcmul((string) $cents, $rate, 4), 0, PHP_ROUND_HALF_UP);
  }

  /** Odds as stored (float column) -> fixed 2-decimal string. */
  public static function odds($odds): string
  {
    return number_format(round((float) $odds, 2), 2, '.', '');
  }

  /**
   * Winning payout exactly as MatchController::updateMatchStatus computes it:
   * floor(bet_amount * odds) with odds rounded to 2 decimals, in PHP floats.
   * (Reproduces the app's float behaviour so stored payouts can be verified.)
   */
  public static function floorPesosTimesOdds($betAmount, $odds): int
  {
    return (int) floor(((float) $betAmount) * round((float) $odds, 2)) * 100;
  }

  /** Earlier app versions: bet_amount * odds, kept to the centavo. */
  public static function centsTimesOdds($betAmount, $odds): int
  {
    return (int) round(((float) $betAmount) * round((float) $odds, 2) * 100);
  }
}
