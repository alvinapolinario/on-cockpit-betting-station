<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class CashIn extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'cash_ins';
    protected $primaryKey = 'cash_in_id';
    public $timestamps = false;
    protected $fillable = [
      'admin_id',
      'event_teller_id',
      'cash_in_amount',
      'cash_in_datetime',
      'cash_in_type',
      'cash_in_status',
  ];
    public function sealedEventId(): ?int
    {
        return static::eventIdForEventTeller($this->event_teller_id);
    }
}
