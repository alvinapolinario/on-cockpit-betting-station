<?php

namespace App\Models\Concerns;

use App\Models\EventClosing;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Blocks creating, changing or deleting records that belong to an event
 * that has already been sealed. Each model tells us its event via
 * sealedEventId().
 *
 * Note: bulk query-builder updates (Model::where()->update()) bypass model
 * events. Every such call in this app runs inside a transaction that also
 * saves a guarded model, so the guard still aborts the whole transaction.
 */
trait GuardsSealedEvent
{
  public static function bootGuardsSealedEvent(): void
  {
    $guard = function ($model) {
      $eventId = $model->sealedEventId();
      if (EventClosing::isSealed($eventId)) {
        throw new RuntimeException("Event #{$eventId} is sealed. Its records can no longer be changed.");
      }
    };

    static::saving($guard);
    static::deleting($guard);
  }

  abstract public function sealedEventId(): ?int;

  protected static function eventIdForEventTeller($eventTellerId): ?int
  {
    if (!$eventTellerId) return null;
    $id = DB::table('event_tellers')->where('event_teller_id', $eventTellerId)->value('event_id');
    return $id === null ? null : (int) $id;
  }

  protected static function eventIdForMatch($matchId): ?int
  {
    if (!$matchId) return null;
    $id = DB::table('matches')->where('match_id', $matchId)->value('event_id');
    return $id === null ? null : (int) $id;
  }
}
