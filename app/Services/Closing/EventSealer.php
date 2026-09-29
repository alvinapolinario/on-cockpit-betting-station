<?php

namespace App\Services\Closing;

use App\Models\Event;
use App\Models\EventClosing;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes an event: freezes it, snapshots it into a closing report, writes the
 * sealed detail file, hashes and signs everything, and records the seal.
 */
class EventSealer
{
  public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

  public function __construct(
    private ClosingReportBuilder $builder,
    private KeyStore $keys,
  ) {}

  /** Live, unsealed report (an X-reading). Nothing is stored. */
  public function preview(int $eventId): array
  {
    [$from, $to] = $this->nextLogRange();
    $report = $this->builder->build($eventId, $from, $to);
    unset($report['_detail_rows'], $report['_log_range']);
    return $report;
  }

  public function seal(int $eventId, int $closedByAccountId, ?int $approvedByAccountId): EventClosing
  {
    $this->keys->signingSecret(); // fail early if the server has no key

    $detailPath = null;
    try {
      $closing = DB::transaction(function () use ($eventId, $closedByAccountId, $approvedByAccountId, &$detailPath) {
        $event = DB::table('events')->where('event_id', $eventId)->lockForUpdate()->first();
        if (!$event) throw new RuntimeException("Event #{$eventId} not found.");
        if (EventClosing::isSealed($eventId)) throw new RuntimeException("Event #{$eventId} is already sealed.");

        $last = EventClosing::orderByDesc('sequence_no')->lockForUpdate()->first();
        $sequence = $last ? $last->sequence_no + 1 : 1;
        $prevHash = $last ? $last->payload_sha256 : self::GENESIS;

        [$from, $to] = $this->nextLogRange();
        $report = $this->builder->build($eventId, $from, $to);

        $failed = array_filter($report['checks'], fn ($c) => !$c['passed'] && in_array($c['name'], ClosingReportBuilder::blockingChecks(), true));
        if ($failed) {
          throw new RuntimeException('Cannot seal: ' . implode('; ', array_map(fn ($c) => "{$c['name']} ({$c['detail']})", $failed)));
        }

        $closedAt = now()->format('Y-m-d H:i:s');
        $detailPath = $this->writeDetailFile($report, $sequence);
        $detailHash = hash_file('sha256', $detailPath);
        $usernames = DB::table('accounts')->pluck('username', 'account_id');

        $payload = [
          'format' => config('sealing.payload_format'),
          'server_id' => config('sealing.server_id'),
          'sequence_no' => $sequence,
          'prev_payload_sha256' => $prevHash,
          'closed_at' => $closedAt,
          'closed_by' => $usernames[$closedByAccountId] ?? "#{$closedByAccountId}",
          'approved_by' => $approvedByAccountId ? ($usernames[$approvedByAccountId] ?? "#{$approvedByAccountId}") : null,
          'event' => $report['event'],
          'fights' => $report['fights'],
          'tellers' => $report['tellers'],
          'totals' => $report['totals'],
          'exceptions' => $report['exceptions'],
          'checks' => $report['checks'],
          'detail' => [
            'sha256' => $detailHash,
            'file' => basename($detailPath),
            'log_range' => $report['_log_range'],
            'counts' => array_map(fn ($rows) => count($rows), $report['_detail_rows']),
          ],
        ];

        $json = Canonical::encode($payload);
        $hash = hash('sha256', $json);
        $signature = base64_encode(sodium_crypto_sign_detached($json, $this->keys->signingSecret()));

        // Freeze the event, then record the seal (after this, the model guards
        // reject any change to the event's fights, bets, claims and cash).
        Event::where('event_id', $eventId)->first()->update(['event_status' => 'Closed']);

        $closing = EventClosing::create([
          'event_id' => $eventId,
          'sequence_no' => $sequence,
          'payload' => $json,
          'payload_sha256' => $hash,
          'detail_sha256' => $detailHash,
          'prev_payload_sha256' => $prevHash,
          'signature' => $signature,
          'key_fingerprint' => $this->keys->signingFingerprint(),
          'seal_code' => self::sealCode($hash),
          'detail_file' => $detailPath,
          'server_id' => config('sealing.server_id'),
          'closed_by_account_id' => $closedByAccountId,
          'approved_by_account_id' => $approvedByAccountId,
          'closed_at' => $closedAt,
        ]);

        $this->log($closedByAccountId, "Event sealed. Event #{$eventId} sequence S" . sprintf('%04d', $sequence)
          . " seal {$closing->seal_code} sha256 {$hash} approved by account #" . ($approvedByAccountId ?? 'n/a'));

        return $closing;
      });
    } catch (\Throwable $e) {
      if ($detailPath && is_file($detailPath)) @unlink($detailPath);
      throw $e;
    }

    self::appendRegistry($closing);
    return $closing;
  }

  /**
   * Append-only record of every seal, kept OUTSIDE the database. A database
   * restore cannot remove it, so restoring a backup that predates a seal
   * (which would silently un-seal an event) is detected and blocked.
   */
  public static function registryPath(): string
  {
    return rtrim(config('sealing.output_path'), '/') . '/seal-registry.jsonl';
  }

  public static function appendRegistry(EventClosing $closing): void
  {
    $dir = dirname(self::registryPath());
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    file_put_contents(self::registryPath(), Canonical::encode([
      'sequence_no' => (int) $closing->sequence_no,
      'event_id' => (int) $closing->event_id,
      'payload_sha256' => $closing->payload_sha256,
      'closed_at' => (string) $closing->closed_at,
    ]) . "\n", FILE_APPEND | LOCK_EX);
  }

  /** @return array<int, string> sequence_no => payload_sha256 */
  public static function registry(): array
  {
    $out = [];
    if (!is_file(self::registryPath())) return $out;
    foreach (file(self::registryPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      $r = json_decode($line, true);
      if ($r) $out[(int) $r['sequence_no']] = $r['payload_sha256'];
    }
    return $out;
  }

  /** Encrypted, signed package for upload to the BIR Compliance System. */
  public function package(EventClosing $closing): array
  {
    $inner = Canonical::encode([
      'format' => config('sealing.package_format'),
      'payload' => $closing->payload,
      'payload_sha256' => $closing->payload_sha256,
      'signature' => $closing->signature,
      'key_fingerprint' => $closing->key_fingerprint,
    ]);

    $outer = [
      'format' => config('sealing.package_format'),
      'server_id' => $closing->server_id,
      'event_id' => $closing->event_id,
      'sequence_no' => $closing->sequence_no,
      'seal_code' => $closing->seal_code,
      'payload_sha256' => $closing->payload_sha256,
      'key_fingerprint' => $closing->key_fingerprint,
      'encryption' => 'libsodium-sealed-box-x25519-xsalsa20poly1305',
      'ciphertext' => base64_encode(sodium_crypto_box_seal($inner, $this->keys->vpsPublic())),
    ];

    $event = $closing->payloadData()['event'];
    $name = sprintf('%s_E%04d_pkg_S%04d_%s_%s.pkg.enc',
      $event['date'], $closing->event_id, $closing->sequence_no,
      date('Ymd\THis', strtotime($closing->closed_at)), $closing->server_id);

    return ['filename' => $name, 'contents' => json_encode($outer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)];
  }

  /**
   * Reference verification, as the VPS must perform it. Used by
   * `php artisan closing:verify` for testing; the VPS team can port this logic.
   */
  public static function verifyPackage(string $contents, string $vpsKeypair, string $serverPublicKey, ?string $expectedPrevHash = null): array
  {
    $results = [];
    $ok = function (string $name, bool $pass, string $detail = '') use (&$results) {
      $results[] = ['name' => $name, 'passed' => $pass, 'detail' => $pass ? 'ok' : $detail];
      return $pass;
    };

    $outer = json_decode($contents, true);
    if (!$ok('package_readable', is_array($outer) && isset($outer['ciphertext']), 'not a closing package')) return $results;

    $inner = sodium_crypto_box_seal_open(base64_decode($outer['ciphertext']), $vpsKeypair);
    if (!$ok('decrypts_with_vps_key', $inner !== false, 'wrong key or ciphertext altered')) return $results;

    $env = json_decode($inner, true);
    $hash = hash('sha256', $env['payload']);
    $ok('hash_matches', $hash === $env['payload_sha256'] && $hash === $outer['payload_sha256'], 'payload hash differs from declared hash');
    $ok('seal_code_matches', self::sealCode($hash) === $outer['seal_code'], 'seal code differs');
    $ok('key_is_registered', $env['key_fingerprint'] === KeyStore::fingerprint($serverPublicKey), 'signed by an unregistered key');
    $sigOk = sodium_crypto_sign_verify_detached(base64_decode($env['signature']), $env['payload'], $serverPublicKey);
    if (!$ok('signature_valid', $sigOk, 'signature invalid: payload was altered or not signed by this server')) return $results;

    $p = json_decode($env['payload'], true);
    if ($expectedPrevHash !== null) {
      $ok('chain_continues', $p['prev_payload_sha256'] === $expectedPrevHash, 'previous-seal hash does not match the last accepted package');
    }

    // Arithmetic re-checks using only the payload.
    $c = fn ($v) => Money::cents($v);
    $sum = fn (array $rows, string $k) => array_sum(array_map(fn ($r) => $c($r[$k]), $rows));
    $t = $p['totals'];
    $ok('fight_rows_balance', collect($p['fights'])->every(fn ($f) =>
        $c($f['net_pool']) === ($c($f['meron_total']) + $c($f['wala_total']))
        && $c($f['payable']) === $c($f['paid']) + $c($f['unclaimed'])
        && (!in_array($f['status'], ['Completed', 'Draw', 'Cancelled'], true)
            || $c($f['net_pool']) === $c($f['refunds']) + $c($f['winnings']) + $c($f['commission']) + $c($f['breakage']))
        && $c($f['house_take']) === $c($f['commission']) + $c($f['breakage'])),
      'a fight row does not balance');
    $ok('totals_equal_fight_sums',
      $c($t['commission']) === $sum($p['fights'], 'commission') && $c($t['winnings']) === $sum($p['fights'], 'winnings')
      && $c($t['refunds']) === $sum($p['fights'], 'refunds') && $c($t['paid']) === $sum($p['fights'], 'paid'),
      'event totals differ from the sum of fights');
    $ok('net_bets_balance', $c($t['net_bets']) === $c($t['gross_bets']) - $c($t['voided_bets'])
      && $c($t['net_bets']) === $c($t['refunds']) + $c($t['winnings']) + $c($t['commission']) + $c($t['breakage']) + $c($t['unsettled_pool']),
      'net bets do not balance');
    $ok('tellers_equal_fights', $sum($p['tellers'], 'net_bets') === $sum($p['fights'], 'net_pool'), 'teller net bets differ from fight pools');
    $ok('teller_rows_balance', collect($p['tellers'])->every(fn ($r) =>
        $c($r['expected_cash']) === $c($r['opening_cash']) + $c($r['cash_in']) + $c($r['net_bets']) - $c($r['payouts']) - $c($r['cash_out'])),
      'a teller row does not balance');
    $ok('commission_rate_applied', collect($p['fights'])->every(fn ($f) => $f['status'] !== 'Completed'
        || $c($f['commission']) === Money::percentOf($c($f['net_pool']), $p['event']['commission_rate'])),
      'commission differs from pool x rate');

    return $results;
  }

  public static function sealCode(string $sha256): string
  {
    return implode('-', str_split(strtoupper(substr($sha256, 0, 16)), 4));
  }

  /** Log entries not yet covered by a previous seal. */
  private function nextLogRange(): array
  {
    $last = EventClosing::orderByDesc('sequence_no')->first();
    $from = $last ? ((json_decode($last->payload, true)['detail']['log_range'][1] ?? 0) + 1) : 1;
    $to = (int) (DB::table('transactions')->max('transaction_id') ?? 0);
    return [$from, max($to, $from - 1)];
  }

  private function writeDetailFile(array $report, int $sequence): string
  {
    $dir = rtrim(config('sealing.output_path'), '/');
    if (!is_dir($dir)) mkdir($dir, 0700, true);

    $path = sprintf('%s/%s_E%04d_detail_S%04d.jsonl', $dir, $report['event']['date'], $report['event']['event_id'], $sequence);
    if (file_exists($path)) throw new RuntimeException("Detail file already exists: {$path}");

    $fh = fopen($path, 'x');
    foreach ($report['_detail_rows'] as $section => $rows) {
      foreach ($rows as $row) {
        fwrite($fh, Canonical::encode(['section' => $section, 'row' => $row]) . "\n");
      }
    }
    fclose($fh);
    chmod($path, 0400);
    return $path;
  }

  private function log(int $accountId, string $message): void
  {
    $log = new Transaction;
    $log->account_id = $accountId;
    $log->transaction_type = 'Web App';
    $log->transaction_message = $message;
    $log->transaction_datetime = date('Y-m-d H:i:s');
    $log->save();
  }
}
