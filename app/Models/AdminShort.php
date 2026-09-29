<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminShort extends Model
{
    use HasFactory;
    protected $table = 'admin_shorts';
    protected $primaryKey = 'admin_short_id';
    public $timestamps = false;
    protected $fillable = [
      'admin_remittance_id',
      'short_amount',
      'short_remarks',
  ];
}
