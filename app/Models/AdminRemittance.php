<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminRemittance extends Model
{
    use HasFactory;
    protected $table = 'admin_remittances';
    protected $primaryKey = 'admin_remittance_id';
    public $timestamps = false;
    protected $fillable = [
      'event_id',
      'denom_1000',
      'denom_500',
      'denom_200',
      'denom_100',
      'denom_50',
      'denom_20',
      'denom_10',
      'denom_5',
      'denom_1',
  ];
}
