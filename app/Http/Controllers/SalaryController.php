<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryController extends Controller
{
  public function index()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Salaries page");
    return view('salaries');
  }

  public function find(Request $r)
  {
    return Salary::where('salary_id', $r->salary_id)->first();
  }

  public function list(Request $r)
  {
    return Salary::where('event_id', $r->event_id)
    ->get();
  }

  public function store(Request $r)
  {
    DB::beginTransaction();

    try {

      if(!empty($r->include_all_tellers))
      {
        $event_tellers = DB::table('event_tellers_view')
        ->where('event_id', $r->event_id)
        ->get();

        foreach($event_tellers as $event_teller)
        {
          $salary = Salary::create([
            'event_id' => $r->event_id,
            'employee_type' => $r->employee_type,
            'employee_name' => $event_teller->teller_name,
            'daily_rate' => $r->daily_rate,
            'overtime_rate' => $r->overtime_rate,
            'total_overtime' => $r->total_overtime,
            'bonus' => $r->bonus,
          ]);
          $this->createLog(session()->get('account_id'), "Web App", "Salaries - Salary created. " . $salary);
        }


      }
      else
      {
        $salary=  Salary::create([
          'event_id' => $r->event_id,
          'employee_type' => $r->employee_type,
          'employee_name' => $r->employee_name,
          'daily_rate' => $r->daily_rate,
          'overtime_rate' => $r->overtime_rate,
          'total_overtime' => $r->total_overtime,
          'bonus' => $r->bonus,
        ]);
        $this->createLog(session()->get('account_id'), "Web App", "Salaries - Salary created. " . $salary);
      }





      DB::commit();


      return 1;

    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }


  public function update(Request $r)
  {
    DB::beginTransaction();

    try {
      $salary = Salary::where('salary_id', $r->uid)->firstOrFail();


      $salary->employee_type = $r->_employee_type;
      $salary->employee_name = $r->_employee_name;
      $salary->daily_rate = $r->_daily_rate;
      $salary->overtime_rate = $r->_overtime_rate;
      $salary->total_overtime = $r->_total_overtime;
      $salary->bonus = $r->_bonus;
      $salary->save();

      DB::commit();


      $this->createLog(session()->get('account_id'), "Web App", "Salaries - Salary updated. " . $salary);

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();
      return 0;
    }
  }


  public function delete(Request $r)
  {
    DB::beginTransaction();

    try {
      $salary = Salary::where('salary_id', $r->salary_id)->first();

      if (!$salary) {
        return 0;
      }

      $salary->delete();

      $this->createLog(session()->get('account_id'), "Web App", "Salaries - Salary deleted. " . $salary);



      DB::commit();

      return 1;
    } catch (\Exception $e) {
      DB::rollBack();

      return 0;
    }
  }
}
