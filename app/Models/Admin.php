<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;
    protected $table = 'admins';
    protected $primaryKey = 'admin_id';
    public $timestamps = false;
    protected $fillable = [
      'account_id',
      'admin_name',
  ];
}
