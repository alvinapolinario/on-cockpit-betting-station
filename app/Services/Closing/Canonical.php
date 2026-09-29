<?php

namespace App\Services\Closing;

/**
 * Canonical JSON: object keys sorted recursively, no escaped slashes/unicode,
 * no whitespace. The same data always produces the same bytes, so the same
 * SHA-256 hash on the betting server and on the VPS.
 */
final class Canonical
{
  public static function encode($data): string
  {
    return json_encode(self::sort($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  private static function sort($v)
  {
    if (is_object($v)) $v = (array) $v;
    if (!is_array($v)) return $v;
    if (!array_is_list($v)) ksort($v, SORT_STRING);
    return array_map([self::class, 'sort'], $v);
  }
}
