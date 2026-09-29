<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class Event extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'events';
    protected $primaryKey = 'event_id';
    public $timestamps = false;
    protected $fillable = [
        'event_name',
        'event_description',
        'event_percentage',
        'event_date',
        'event_status',
        'admin_cash_on_hand',
        'teller_initial_cash_on_hand',
        'prizes',
        'rd'
    ];
    public function sealedEventId(): ?int
    {
        return $this->event_id === null ? null : (int) $this->event_id;
    }
}
