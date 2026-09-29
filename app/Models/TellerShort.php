<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TellerShort extends Model
{
    use HasFactory;
    protected $table = 'teller_shorts';
    protected $primaryKey = 'teller_short_id';
    public $timestamps = false;
    protected $fillable = [
      'teller_remittance_id',
      'short_amount',
      'short_remarks',
  ];
}
