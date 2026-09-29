<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GuardsSealedEvent;

class CashOut extends Model
{
    use HasFactory, GuardsSealedEvent;
    protected $table = 'cash_outs';
    protected $primaryKey = 'cash_out_id';
    public $timestamps = false;
    protected $fillable = [
      'event_teller_id',
      'admin_id',
      'cash_out_amount',
      'cash_out_datetime',
      'cash_out_status',
  ];
    public function sealedEventId(): ?int
    {
        return static::eventIdForEventTeller($this->event_teller_id);
    }
}
