<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teller extends Model
{
    use HasFactory;
    protected $table = 'tellers';
    protected $primaryKey = 'teller_id';
    public $timestamps = false;
    protected $fillable = [
      'account_id',
      'contact_number',
      'teller_name',
      'phone_uid'
  ];
}
