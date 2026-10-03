<?php

namespace App\Services\Bridge;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Messages between the betting station and the matching system (same server).
 *
 * Outgoing (betting → matching): fight status (open / closed / held) and
 * results. Every message is first written to bridge_outbox inside the same
 * transaction as the change it describes, then delivered in order and retried
 * until matching confirms it. Payouts never wait for matching.
 *
 * Messages are signed: X-Bridge-Timestamp + X-Bridge-Signature =
 * hex(HMAC-SHA256(key, timestamp + "." + raw body)), one key per direction.
 */
class MatchingBridge
{
  public static function enabled(): bool
  {
    return (bool) config('bridge.enabled') && config('bridge.matching_url') !== ''
      && config('bridge.key_to_matching') !== '' && config('bridge.key_from_matching') !== '';
  }

  /** The active event is linked to a matching event (fights come from matching). */
  public static function eventLinked($event): bool
  {
    return self::enabled() && $event && !empty($event->matching_event_id);
  }

  public static function fightLinked($match): bool
  {
    return self::enabled() && $match && !empty($match->source_fight_uid);
  }

  // ------------------------------------------------------------- outgoing

  /** Queue a message (call inside the transaction of the change). */
  public static function queue(string $type, array $payload): string
  {
    $key = "betting-{$type}-" . ($payload['fight_uid'] ?? 'x') . '-' . Str::uuid();
    DB::table('bridge_outbox')->insert([
      'msg_key' => $key,
      'type' => $type,
      'fight_uid' => $payload['fight_uid'] ?? null,
      'payload' => json_encode($payload + ['msg_key' => $key, 'type' => $type], JSON_UNESCAPED_UNICODE),
      'created_at' => now(),
    ]);
    return $key;
  }

  /** Fight status for matching: open / closed / held. */
  public static function queueStatus($match, string $state, ?string $reason = null): void
  {
    if (!self::fightLinked($match)) return;
    self::queue('status', [
      'fight_uid' => (int) $match->source_fight_uid,
      'fight_no' => (int) $match->match_number,
      'state' => $state,
      'hold_reason' => $reason,
      'meron_total' => (string) $match->meron_total_bet,
      'wala_total' => (string) $match->wala_total_bet,
      'meron_odds' => $match->meron_odds,
      'wala_odds' => $match->wala_odds,
      'at' => now()->format('Y-m-d H:i:s'),
    ]);
  }

  /** Result (version 1) or correction (version 2+) for matching. */
  public static function queueResult($match, string $result, ?string $previous, ?string $by): void
  {
    if (!self::fightLinked($match)) return;
    $version = DB::table('bridge_outbox')->where('type', 'result')->where('fight_uid', (int) $match->source_fight_uid)->count() + 1;
    self::queue('result', [
      'fight_uid' => (int) $match->source_fight_uid,
      'fight_no' => (int) $match->match_number,
      'result' => $result,               // meron | wala | draw | cancelled (betting sides)
      'version' => $version,
      'previous' => $previous,
      'declared_by' => $by,
      'at' => now()->format('Y-m-d H:i:s'),
    ]);
  }

  /** Deliver pending messages in order; stops at the first failure so order is kept. */
  public static function flush(int $max = 50): array
  {
    $sent = 0;
    $error = null;
    if (!self::enabled()) return ['sent' => 0, 'pending' => 0, 'error' => 'bridge disabled'];

    $rows = DB::table('bridge_outbox')->whereNull('sent_at')->orderBy('id')->limit($max)->get();
    foreach ($rows as $row) {
      try {
        $res = self::post('/bridge/betting/' . $row->type, $row->payload);
        if ($res->successful() || $res->status() === 409) {
          // 409 = matching already has a newer state / refuses permanently: keep the reason, do not block the queue.
          DB::table('bridge_outbox')->where('id', $row->id)->update([
            'sent_at' => now(), 'attempts' => $row->attempts + 1,
            'last_error' => $res->successful() ? null : ('refused: ' . substr($res->body(), 0, 500)),
          ]);
          $sent++;
          continue;
        }
        $error = "HTTP {$res->status()}: " . substr($res->body(), 0, 300);
      } catch (Throwable $e) {
        $error = $e->getMessage();
      }
      DB::table('bridge_outbox')->where('id', $row->id)->update(['attempts' => $row->attempts + 1, 'last_error' => substr((string) $error, 0, 1000)]);
      break;
    }

    return ['sent' => $sent, 'pending' => DB::table('bridge_outbox')->whereNull('sent_at')->count(), 'error' => $error];
  }

  public static function status(): array
  {
    if (!self::enabled()) return ['enabled' => false];
    $pending = DB::table('bridge_outbox')->whereNull('sent_at');
    $oldest = (clone $pending)->orderBy('id')->first();
    return [
      'enabled' => true,
      'pending' => (clone $pending)->count(),
      'last_error' => $oldest->last_error ?? null,
      'oldest_pending_at' => $oldest->created_at ?? null,
    ];
  }

  private static function post(string $path, string $body)
  {
    $ts = (string) time();
    return Http::timeout(3)->connectTimeout(2)->withHeaders([
      'X-Bridge-Timestamp' => $ts,
      'X-Bridge-Signature' => hash_hmac('sha256', $ts . '.' . $body, config('bridge.key_to_matching')),
    ])->withBody($body, 'application/json')->post(config('bridge.matching_url') . $path);
  }

  // ------------------------------------------------------------- incoming

  /** True when the request was signed by matching with the shared key. */
  public static function verify(Request $r): bool
  {
    $key = (string) config('bridge.key_from_matching');
    $ts = (string) $r->header('X-Bridge-Timestamp', '');
    $sig = (string) $r->header('X-Bridge-Signature', '');
    if ($key === '' || !ctype_digit($ts) || abs(time() - (int) $ts) > (int) config('bridge.max_clock_skew')) return false;
    return hash_equals(hash_hmac('sha256', $ts . '.' . $r->getContent(), $key), $sig);
  }
}
