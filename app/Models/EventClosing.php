<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EventClosing extends Model
{
  protected $table = 'event_closings';
  protected $primaryKey = 'event_closing_id';
  public $timestamps = false;

  protected $guarded = ['event_closing_id'];

  // Only these may change after sealing (download / acknowledgment tracking).
  public const MUTABLE = [
    'downloaded_at', 'downloaded_by_account_id', 'ack_code', 'ack_at', 'ack_by_account_id',
  ];

  protected static function booted(): void
  {
    static::updating(function (EventClosing $closing) {
      $changed = array_diff(array_keys($closing->getDirty()), self::MUTABLE);
      if (!empty($changed)) {
        throw new RuntimeException('Sealed event closings cannot be modified.');
      }
    });

    static::deleting(function () {
      throw new RuntimeException('Sealed event closings cannot be deleted.');
    });
  }

  public function event()
  {
    return $this->belongsTo(Event::class, 'event_id', 'event_id');
  }

  public function payloadData(): array
  {
    return json_decode($this->payload, true);
  }

  public function status(): string
  {
    if ($this->ack_code) return 'Acknowledged';
    if ($this->downloaded_at) return 'Downloaded';
    return 'Sealed';
  }

  public static function isSealed(?int $eventId): bool
  {
    return $eventId !== null && static::where('event_id', $eventId)->exists();
  }
}
