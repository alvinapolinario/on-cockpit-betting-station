<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = 'transactions';
    protected $primaryKey = 'transaction_id';
    public $timestamps = false;
    protected $fillable = [
      'account_id',
      'transaction_type',
      'transaction_message',
      'transaction_datetime'
  ];
}
