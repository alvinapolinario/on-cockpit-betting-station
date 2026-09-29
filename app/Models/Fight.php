<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class Fight extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'matches';
    protected $primaryKey = 'match_id';
    public $timestamps = false;
    protected $fillable = [
        'event_id',
        'match_number',
        'match_winner',
        'meron_total_bet',
        'wala_total_bet',
        'meron_odds',
        'wala_odds',
        'meron_bet_status',
        'wala_bet_status',
        'match_status',
        'match_bet_status',
        'match_created_datetime',
        'is_display',
        'meron_entry',
        'wala_entry',
    ];
    public function sealedEventId(): ?int
    {
        return $this->event_id === null ? null : (int) $this->event_id;
    }
}
