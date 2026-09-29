<?php

namespace App\Services\Closing;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reconstructs closing reports for PAST events from database backup files
 * (one backup = one event day) and seals them as LEGACY packages.
 *
 * Legacy packages are clearly distinguished from live seals:
 *  - their own sender identity (config sealing.legacy_server_id) and signing key,
 *  - their own sequence chain, in event-date order,
 *  - an "origin" section naming the backup file, its SHA-256 and when it was reconstructed,
 *  - checks are recorded but never block sealing (history cannot be corrected).
 *
 * The backup must already be loaded into the "legacy" scratch database
 * (sabong_legacy_tmp); the live database is never read or changed.
 */
class LegacySealer
{
  public function __construct(private ClosingReportBuilder $builder, private KeyStore $keys) {}

  public static function registryPath(): string
  {
    return rtrim(config('sealing.output_path'), '/') . '/legacy/registry.jsonl';
  }

  /** @return array<int, array> registry entries in sequence order */
  public static function registry(): array
  {
    if (!is_file(self::registryPath())) return [];
    return array_map(fn ($l) => json_decode($l, true), file(self::registryPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
  }

  public function sealLoadedBackup(string $fileName, string $fileSha256): array
  {
    foreach (self::registry() as $r) {
      if ($r['file_sha256'] === $fileSha256) return $r + ['skipped' => 'already sealed'];
    }

    config(['database.connections.legacy' => array_merge(config('database.connections.mysql'), ['database' => env('LEGACY_DB_DATABASE', 'sabong_legacy_tmp')])]);
    DB::purge('legacy');
    $db = DB::connection('legacy');

    $eventId = $db->table('matches')->select('event_id')->groupBy('event_id')->orderByRaw('COUNT(*) DESC')->value('event_id');
    if (!$eventId) throw new RuntimeException("{$fileName}: no fights found in this backup.");
    $logTo = (int) ($db->table('transactions')->max('transaction_id') ?? 0);

    $report = $this->builder->on('legacy')->build((int) $eventId, 1, $logTo);

    $registry = self::registry();
    $last = end($registry) ?: null;
    $sequence = $last ? $last['sequence_no'] + 1 : 1;
    $prevHash = $last ? $last['payload_sha256'] : EventSealer::GENESIS;
    $serverId = config('sealing.legacy_server_id');
    $now = now()->format('Y-m-d H:i:s');

    $dir = rtrim(config('sealing.output_path'), '/') . '/legacy';
    if (!is_dir($dir . '/packages')) mkdir($dir . '/packages', 0700, true);
    $tag = sprintf('%s_L%04d', $report['event']['date'], $sequence);

    // Detail records (fingerprinted, kept here), exactly like live seals.
    $detailPath = "{$dir}/{$tag}_detail.jsonl";
    $fh = fopen($detailPath, 'x');
    foreach ($report['_detail_rows'] as $section => $rows) {
      foreach ($rows as $row) fwrite($fh, Canonical::encode(['section' => $section, 'row' => $row]) . "\n");
    }
    fclose($fh);

    $payload = [
      'format' => config('sealing.payload_format'),
      'server_id' => $serverId,
      'sequence_no' => $sequence,
      'prev_payload_sha256' => $prevHash,
      'closed_at' => $now,
      'closed_by' => 'legacy-import',
      'approved_by' => null,
      'origin' => [
        'type' => 'legacy_backup',
        'note' => 'Reconstructed after the event from a database backup. Not witnessed or sealed at the arena at the time.',
        'backup_file' => $fileName,
        'backup_sha256' => $fileSha256,
        'reconstructed_at' => $now,
      ],
      'event' => $report['event'],
      'fights' => $report['fights'],
      'tellers' => $report['tellers'],
      'totals' => $report['totals'],
      'exceptions' => $report['exceptions'],
      'checks' => $report['checks'],
      'detail' => [
        'sha256' => hash_file('sha256', $detailPath),
        'file' => basename($detailPath),
        'log_range' => [1, $logTo],
        'counts' => array_map(fn ($rows) => count($rows), $report['_detail_rows']),
      ],
    ];

    $json = Canonical::encode($payload);
    $hash = hash('sha256', $json);
    $signature = base64_encode(sodium_crypto_sign_detached($json, $this->keys->legacySecret()));
    $sealCode = EventSealer::sealCode($hash);

    $inner = Canonical::encode([
      'format' => config('sealing.package_format'),
      'payload' => $json,
      'payload_sha256' => $hash,
      'signature' => $signature,
      'key_fingerprint' => $this->keys->legacyFingerprint(),
    ]);
    $outer = [
      'format' => config('sealing.package_format'),
      'server_id' => $serverId,
      'event_id' => $report['event']['event_id'],
      'sequence_no' => $sequence,
      'seal_code' => $sealCode,
      'payload_sha256' => $hash,
      'key_fingerprint' => $this->keys->legacyFingerprint(),
      'encryption' => 'libsodium-sealed-box-x25519-xsalsa20poly1305',
      'ciphertext' => base64_encode(sodium_crypto_box_seal($inner, $this->keys->vpsPublic())),
    ];
    $packagePath = "{$dir}/packages/{$tag}_{$serverId}.pkg.enc";
    file_put_contents($packagePath, json_encode($outer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    $entry = [
      'sequence_no' => $sequence, 'event_date' => $report['event']['date'], 'event_name' => $report['event']['name'],
      'file' => $fileName, 'file_sha256' => $fileSha256, 'payload_sha256' => $hash, 'seal_code' => $sealCode,
      'package' => basename($packagePath), 'sealed_at' => $now,
      'fights' => $report['totals']['fights_total'], 'net_bets' => $report['totals']['net_bets'],
      'house_take' => $report['totals']['house_take'],
      'flags' => array_values(array_map(fn ($c) => $c['name'], array_filter($report['checks'], fn ($c) => !$c['passed']))),
    ];
    file_put_contents(self::registryPath(), json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    return $entry;
  }
}
