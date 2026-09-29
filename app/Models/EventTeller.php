<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class EventTeller extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'event_tellers';
    protected $primaryKey = 'event_teller_id';
    public $timestamps = false;
    protected $fillable = [
      'event_teller_id',
      'event_id',
      'teller_id',
      'teller_balance',
      'teller_match_balance',

  ];
    public function sealedEventId(): ?int
    {
        return $this->event_id === null ? null : (int) $this->event_id;
    }
}
