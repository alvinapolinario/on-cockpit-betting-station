<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
  use HasFactory;
  protected $table = 'accounts';
  protected $primaryKey = 'account_id';
  public $timestamps = false;
  protected $fillable = [
    'username',
    'password',
    'account_type',
    'is_active',
    'account_date_created'
];
}
