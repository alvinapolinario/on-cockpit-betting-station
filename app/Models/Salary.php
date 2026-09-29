<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory;
    protected $table = 'salaries';
    protected $primaryKey = 'salary_id';
    public $timestamps = false;
    protected $fillable = [
      'event_id',
      'employee_type',
      'employee_name',
      'daily_rate',
      'overtime_rate',
      'total_overtime',
      'bonus',
  ];
}
