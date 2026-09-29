<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class Bet extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'bets';
    protected $primaryKey = 'bet_id';
    public $timestamps = false;
    protected $fillable = [
      'event_teller_id',
      'match_id',
      'bet_receipt_code',
      'bet_side',
      'bet_amount',
      'bet_win_amount',
      'bet_payout_amount',
      'bet_payout_datetime',
      'bet_status',
      'bet_datetime',
      'bet_is_winner',
      'bet_void_datetime',
      'bet_payout_by',
  ];
    public function sealedEventId(): ?int
    {
        return static::eventIdForMatch($this->match_id);
    }
}
