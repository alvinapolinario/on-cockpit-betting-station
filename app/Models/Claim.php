<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class Claim extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'claims';
    protected $primaryKey = 'claim_id';
    public $timestamps = false;
    protected $fillable = [
      'bet_id',
      'event_teller_id',
      'claim_amount',
      'claim_datetime',
  ];
    public function sealedEventId(): ?int
    {
        return static::eventIdForEventTeller($this->event_teller_id);
    }
}
