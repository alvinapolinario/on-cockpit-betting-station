<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Splits a mysqldump / mariadb-dump / SQLyog .sql file into statements and
 * checks every statement against a whitelist of what a normal single-database
 * dump contains. Anything else (another database, users, grants, file access,
 * global settings) is rejected before a single statement is executed.
 *
 * TESTING ONLY: used by the raw SQL import, which is disabled in production.
 */
final class SqlDumpReader
{
  /** Statement types a dump of this application's database legitimately contains. */
  private const ALLOWED = [
    '/^SET\s+(?!GLOBAL\b|PASSWORD\b|@@GLOBAL\.)/i',
    '/^(DROP|CREATE)\s+TABLE\b/i',
    '/^DROP\s+(VIEW|FUNCTION|PROCEDURE|TRIGGER)\b/i',
    '/^CREATE\s+(OR\s+REPLACE\s+)?(ALGORITHM\s*=\s*\w+\s+)?(SQL\s+SECURITY\s+\w+\s+)?VIEW\b/i',
    '/^CREATE\s+(FUNCTION|PROCEDURE|TRIGGER)\b/i',
    '/^(INSERT|REPLACE)\s+(IGNORE\s+)?INTO\b/i',
    '/^(LOCK|UNLOCK)\s+TABLES\b/i',
    '/^ALTER\s+TABLE\b/i',
  ];

  /** Never allowed anywhere, including inside routine bodies. */
  private const FORBIDDEN = '/\b(INTO\s+OUTFILE|INTO\s+DUMPFILE|LOAD_FILE\s*\(|LOAD\s+DATA)\b/i';

  public static function read(string $path): string
  {
    $data = str_ends_with($path, '.gz') ? @gzdecode((string) file_get_contents($path)) : file_get_contents($path);
    if ($data === false || $data === '') throw new RuntimeException('The SQL file is empty or could not be read.');
    return $data;
  }

  /** @return \Generator<int, string> statements, with DEFINER clauses removed */
  public static function statements(string $sql): \Generator
  {
    $n = strlen($sql);
    $delim = ';';
    $buf = '';
    $quote = null;
    $inBlock = false;
    $inLine = false;

    for ($i = 0; $i < $n; $i++) {
      $c = $sql[$i];

      if ($inLine) {
        if ($c === "\n") { $inLine = false; $buf .= "\n"; }
        continue;
      }
      if ($inBlock) {
        $buf .= $c;
        if ($c === '*' && ($sql[$i + 1] ?? '') === '/') { $buf .= '/'; $i++; $inBlock = false; }
        continue;
      }
      if ($quote !== null) {
        $buf .= $c;
        if ($c === '\\' && $quote !== '`') { $buf .= $sql[$i + 1] ?? ''; $i++; }
        elseif ($c === $quote) $quote = null;
        continue;
      }

      // DELIMITER is a client command at the start of a line.
      if (trim($buf) === '' && ($i === 0 || $sql[$i - 1] === "\n")
        && preg_match('/\GDELIMITER[ \t]+(\S+)[^\n]*/i', $sql, $m, 0, $i)) {
        $delim = $m[1];
        $i += strlen($m[0]) - 1;
        $buf = '';
        continue;
      }

      if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $buf .= $c; continue; }
      if ($c === '/' && ($sql[$i + 1] ?? '') === '*') { $inBlock = true; $buf .= '/*'; $i++; continue; }
      if ($c === '#' || ($c === '-' && ($sql[$i + 1] ?? '') === '-' && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\n", "\r"], true))) {
        $inLine = true;
        continue;
      }
      if ($c === $delim[0] && substr($sql, $i, strlen($delim)) === $delim) {
        $stmt = trim($buf);
        if ($stmt !== '' && !self::isOnlyComment($stmt)) yield self::stripDefiner($stmt);
        $buf = '';
        $i += strlen($delim) - 1;
        continue;
      }
      $buf .= $c;
    }

    if ($quote !== null || $inBlock) throw new RuntimeException('The SQL file ends inside an unterminated string or comment (truncated file?).');
    $stmt = trim($buf);
    if ($stmt !== '' && !self::isOnlyComment($stmt)) yield self::stripDefiner($stmt);
  }

  /**
   * Parses the whole file and checks every statement without executing anything.
   * @return array{statements:int, tables:int, views:int, routines:int, triggers:int, inserts:int}
   */
  public static function validate(string $sql, string $database): array
  {
    $s = ['statements' => 0, 'tables' => 0, 'views' => 0, 'routines' => 0, 'triggers' => 0, 'inserts' => 0];
    foreach (self::statements($sql) as $k => $stmt) {
      $s['statements']++;
      if (self::isSameDatabaseSelect($stmt, $database)) continue;
      $lead = self::leading($stmt);
      $ok = false;
      foreach (self::ALLOWED as $re) {
        if (preg_match($re, $lead)) { $ok = true; break; }
      }
      if (!$ok) {
        throw new RuntimeException('Statement #' . ($k + 1) . ' is not allowed in an import: "' . mb_substr(preg_replace('/\s+/', ' ', $lead), 0, 80) . '…"');
      }
      if (!preg_match('/^(INSERT|REPLACE)\b/i', $lead) && preg_match(self::FORBIDDEN, $stmt)) {
        throw new RuntimeException('Statement #' . ($k + 1) . ' tries to read or write server files.');
      }
      if (preg_match('/^CREATE\s+TABLE\b/i', $lead)) $s['tables']++;
      elseif (preg_match('/^CREATE\b.*\bVIEW\b/is', substr($lead, 0, 120))) $s['views']++;
      elseif (preg_match('/^CREATE\s+(FUNCTION|PROCEDURE)\b/i', $lead)) $s['routines']++;
      elseif (preg_match('/^CREATE\s+TRIGGER\b/i', $lead)) $s['triggers']++;
      elseif (preg_match('/^(INSERT|REPLACE)\b/i', $lead)) $s['inserts']++;
    }
    if ($s['tables'] === 0) throw new RuntimeException('No CREATE TABLE statements found: this does not look like a full database dump.');
    return $s;
  }

  /**
   * "CREATE DATABASE IF NOT EXISTS `x`" / "USE `x`" for the SAME database the
   * import targets: harmless, skipped by the importer. Any other database is rejected.
   */
  public static function isSameDatabaseSelect(string $stmt, string $database): bool
  {
    $lead = self::leading($stmt);
    return (bool) preg_match('/^(CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS|USE)\s+`?([A-Za-z0-9_]+)`?/i', $lead, $m)
      && $m[2] === $database;
  }

  /** Statement text with leading comments removed and a version comment (/*!50003 ...) unwrapped. */
  private static function leading(string $stmt): string
  {
    $t = ltrim($stmt);
    while (true) {
      if (preg_match('#^/\*![0-9]*\s*#', $t, $m)) { $t = ltrim(substr($t, strlen($m[0]))); continue; }
      if (str_starts_with($t, '/*') && ($end = strpos($t, '*/')) !== false) { $t = ltrim(substr($t, $end + 2)); continue; }
      break;
    }
    // Version comments can also appear mid-statement, e.g. mariadb-dump views:
    // CREATE ALGORITHM=UNDEFINED */ /*!50013 SQL SECURITY DEFINER */ /*!50001 VIEW ...
    return ltrim(preg_replace('#/\*![0-9]*|\*/#', ' ', $t));
  }

  /**
   * True when the text is nothing but plain comments. Comments are removed one
   * at a time from the front; executable version comments (/*!40101 ... *\/)
   * are never removed, so a real statement that merely follows a comment
   * (e.g. mariadb-dump's first line "/*M!999999\- enable the sandbox mode *\/",
   * which has no semicolon) is kept.
   */
  private static function isOnlyComment(string $stmt): bool
  {
    $t = ltrim($stmt);
    while (str_starts_with($t, '/*') && !str_starts_with($t, '/*!')) {
      $end = strpos($t, '*/');
      if ($end === false) return false;
      $t = ltrim(substr($t, $end + 2));
    }
    return $t === '';
  }

  private static function stripDefiner(string $stmt): string
  {
    return preg_replace('/\sDEFINER\s*=\s*(`[^`]*`|\'[^\']*\'|\w+)@(`[^`]*`|\'[^\']*\'|[\w.%-]+)/', '', $stmt);
  }
}
