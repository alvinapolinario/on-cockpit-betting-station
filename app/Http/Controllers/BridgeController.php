<?php

namespace App\Http\Controllers;

use App\Events\MatchesUpdated;
use App\Events\MatchUpdated;
use App\Models\Event;
use App\Models\EventTeller;
use App\Models\Fight;
use App\Services\Bridge\MatchingBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Messages from the matching system (same server). Matching CALLS the next
 * fight (entries, owners, weights, bands) or RECALLS it before betting opens.
 * The betting operator then opens/closes betting and declares the result here;
 * those go back to matching through MatchingBridge's outbox.
 */
class BridgeController extends Controller
{
  public function ping(Request $r)
  {
    if (!MatchingBridge::enabled()) return response()->json(['ok' => false, 'message' => 'Matching link is disabled on the betting station.'], 503);
    if (!MatchingBridge::verify($r)) return response()->json(['ok' => false, 'message' => 'Invalid signature.'], 401);
    $event = Event::where('event_status', 'Active')->first();
    return response()->json(['ok' => true, 'active_event' => $event ? ['id' => $event->event_id, 'name' => $event->event_name, 'matching_event_id' => $event->matching_event_id] : null]);
  }

  public function call(Request $r)
  {
    return $this->handle($r, 'call', fn (array $m) => $this->applyCall($m));
  }

  public function recall(Request $r)
  {
    return $this->handle($r, 'recall', fn (array $m) => $this->applyRecall($m));
  }

  /** Signature check, de-duplication (same msg_key = same answer), logging. */
  private function handle(Request $r, string $type, callable $apply)
  {
    if (!MatchingBridge::enabled()) return response()->json(['ok' => false, 'message' => 'Matching link is disabled on the betting station.'], 503);
    if (!MatchingBridge::verify($r)) return response()->json(['ok' => false, 'message' => 'Invalid signature.'], 401);

    $m = json_decode($r->getContent(), true);
    $key = is_array($m) ? (string) ($m['msg_key'] ?? '') : '';
    if ($key === '' || strlen($key) > 100) return response()->json(['ok' => false, 'message' => 'Missing msg_key.'], 422);

    $seen = DB::table('bridge_inbox')->where('msg_key', $key)->first();
    if ($seen) return response($seen->response, $seen->http_status)->header('Content-Type', 'application/json');

    try {
      [$status, $body, $broadcast] = DB::transaction(function () use ($apply, $m) {
        return $apply($m);
      });
    } catch (Throwable $e) {
      report($e);
      return response()->json(['ok' => false, 'message' => 'Betting station error: ' . $e->getMessage()], 500);
    }

    DB::table('bridge_inbox')->insertOrIgnore([
      'msg_key' => $key, 'type' => $type, 'payload' => json_encode($m, JSON_UNESCAPED_UNICODE),
      'http_status' => $status, 'response' => json_encode($body, JSON_UNESCAPED_UNICODE), 'received_at' => now(),
    ]);
    $this->createLog(null, 'Matching Link', strtoupper($type) . ' ' . ($status < 300 ? 'accepted' : 'refused') . ': ' . ($body['message'] ?? '') . ' [fight uid ' . ($m['fight']['uid'] ?? $m['fight_uid'] ?? '?') . ']');

    if ($broadcast) $this->broadcastFights($broadcast);
    return response()->json($body, $status);
  }

  private function refuse(string $message, int $status = 409): array
  {
    return [$status, ['ok' => false, 'message' => $message], null];
  }

  /** Matching called a fight: create it (or update it while betting has not opened). */
  private function applyCall(array $m): array
  {
    $f = $m['fight'] ?? null;
    $ev = $m['event'] ?? null;
    $uid = (int) ($f['uid'] ?? 0);
    $no = (int) ($f['no'] ?? 0);
    if (!$uid || $no < 1 || !is_array($f['meron'] ?? null) || !is_array($f['wala'] ?? null) || !(int) ($ev['id'] ?? 0)) {
      return $this->refuse('Incomplete fight details.', 422);
    }

    $event = Event::where('event_status', 'Active')->lockForUpdate()->first();
    if (!$event) return $this->refuse('No active event on the betting station. Activate the event first.');

    // One matching derby per betting event.
    if (empty($event->matching_event_id)) {
      $event->matching_event_id = (int) $ev['id'];
      $event->save();
      $this->createLog(null, 'Matching Link', "Betting event #{$event->event_id} linked to matching event #{$ev['id']} ({$ev['name']})");
    } elseif ((int) $event->matching_event_id !== (int) $ev['id']) {
      return $this->refuse("The active betting event is linked to another matching event (#{$event->matching_event_id}).");
    }

    $details = fn (array $s) => json_encode([
      'entry' => (string) ($s['entry'] ?? ''), 'owner' => (string) ($s['owner'] ?? ''), 'weight' => $s['weight'] ?? null,
      'wingband' => (string) ($s['wingband'] ?? ''), 'legband' => (string) ($s['legband'] ?? ''), 'type' => (string) ($s['type'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
    $version = (int) ($f['version'] ?? 1);
    $fields = [
      'match_number' => $no,
      'meron_entry' => mb_substr((string) ($f['meron']['entry'] ?? ''), 0, 255),
      'wala_entry' => mb_substr((string) ($f['wala']['entry'] ?? ''), 0, 255),
      'meron_details' => $details($f['meron']),
      'wala_details' => $details($f['wala']),
      'source_call_version' => $version,
      'hold_reason' => null,
    ];

    $existing = Fight::where('event_id', $event->event_id)->where('source_fight_uid', $uid)->lockForUpdate()->first();
    if ($existing) {
      if (in_array($existing->match_status, ['Completed', 'Draw', 'Cancelled'], true)) {
        return $this->refuse("Fight #{$existing->match_number} was already played (" . $existing->match_status . ').');
      }
      if ($existing->bet_opened_at || $this->hasBets($existing)) {
        return $this->refuse("Fight #{$existing->match_number}: betting already opened, so it can no longer be changed from matching.");
      }
      if ($version < (int) $existing->source_call_version) return [200, ['ok' => true, 'message' => 'Older call ignored.', 'match_id' => $existing->match_id], null];
      if ($conflict = $this->numberTaken($event->event_id, $no, $existing->match_id)) return $this->refuse($conflict);
      $existing->fill($fields)->save();
      return [200, ['ok' => true, 'message' => "Fight #{$no} updated.", 'match_id' => $existing->match_id], $existing];
    }

    // Only one fight in play at a time.
    $ongoing = Fight::where('event_id', $event->event_id)->where('match_status', 'Ongoing')->orderByDesc('match_id')->lockForUpdate()->first();
    if ($ongoing && ($ongoing->source_fight_uid || $ongoing->bet_opened_at || $this->hasBets($ongoing))) {
      return $this->refuse("Fight #{$ongoing->match_number} is still in play on the betting station. Finish or cancel it first.");
    }
    if ($conflict = $this->numberTaken($event->event_id, $no, $ongoing->match_id ?? null)) return $this->refuse($conflict);

    EventTeller::where('event_id', $event->event_id)->update(['teller_match_balance' => 0]);
    $base = $fields + ['source_fight_uid' => $uid, 'called_at' => now(), 'match_bet_status' => 'Closed', 'match_status' => 'Ongoing', 'is_display' => 0];

    if ($ongoing) {
      // An empty placeholder fight (created by "View Matches") becomes the called fight.
      $ongoing->fill($base)->save();
      $fight = $ongoing;
    } else {
      $fight = Fight::create($base + [
        'event_id' => $event->event_id, 'match_winner' => '', 'meron_total_bet' => 0, 'wala_total_bet' => 0,
        'meron_odds' => 0, 'wala_odds' => 0, 'meron_bet_status' => 0, 'wala_bet_status' => 0,
        'match_created_datetime' => date('Y-m-d H:i:s'),
      ]);
    }
    return [201, ['ok' => true, 'message' => "Fight #{$no} is ready on the betting station.", 'match_id' => $fight->match_id], $fight];
  }

  /** Matching withdrew the called fight (allowed only before betting opens). */
  private function applyRecall(array $m): array
  {
    $uid = (int) ($m['fight_uid'] ?? 0);
    $event = Event::where('event_status', 'Active')->first();
    $fight = $event ? Fight::where('event_id', $event->event_id)->where('source_fight_uid', $uid)->lockForUpdate()->first() : null;
    if (!$fight) return [200, ['ok' => true, 'message' => 'Fight is not on the betting station (nothing to recall).'], null];
    if ($fight->match_status !== 'Ongoing' || $fight->bet_opened_at || $this->hasBets($fight)) {
      return $this->refuse("Fight #{$fight->match_number}: betting already opened. Only the betting operator can cancel it (bets are refunded).");
    }
    $no = $fight->match_number;
    $fight->delete();
    return [200, ['ok' => true, 'message' => "Fight #{$no} withdrawn from the betting station."], Fight::where('event_id', $event->event_id)->orderByDesc('match_id')->first() ?? true];
  }

  private function hasBets(Fight $f): bool
  {
    return DB::table('bets')->where('match_id', $f->match_id)->exists();
  }

  private function numberTaken(int $eventId, int $no, ?int $exceptMatchId): ?string
  {
    $q = Fight::where('event_id', $eventId)->where('match_number', $no);
    if ($exceptMatchId) $q->where('match_id', '<>', $exceptMatchId);
    return $q->exists() ? "Fight number #{$no} is already used on the betting station for this event." : null;
  }

  private function broadcastFights($fight): void
  {
    try {
      $event = Event::where('event_status', 'Active')->first();
      if ($fight instanceof Fight) broadcast(new MatchUpdated($fight));
      if ($event) broadcast(new MatchesUpdated(Fight::where('event_id', $event->event_id)->orderBy('match_id', 'desc')->get()));
    } catch (Throwable $e) {
      report($e); // live screens refresh on their own; never fail the call because of the websocket
    }
  }
}
