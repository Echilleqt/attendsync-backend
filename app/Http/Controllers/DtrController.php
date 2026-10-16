<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\DtrService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DtrController extends Controller
{
    public function __construct(protected DtrService $dtrService)
    {
    }

    public function index(Request $request)
    {
        $employees = Employee::orderBy('last_name')->get();

        $selectedEmployeeId = $request->query('employee_id', $employees->first()?->id);
        $month = $request->query('month', Carbon::now('Asia/Manila')->format('Y-m'));

        $selectedEmployee = $selectedEmployeeId ? Employee::find($selectedEmployeeId) : null;
        $dtrData = null;

        if ($selectedEmployee) {
            $dtrData = $this->dtrService->generateMonthlyDtr($selectedEmployee, $month);
        }

        return view('admin.reports.dtr', compact('employees', 'selectedEmployee', 'month', 'dtrData'));
    }

    public function print(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $month = $request->query('month', Carbon::now('Asia/Manila')->format('Y-m'));

        $employee = Employee::findOrFail($employeeId);
        $dtrData = $this->dtrService->generateMonthlyDtr($employee, $month);

        return view('admin.reports.dtr_print', compact('employee', 'month', 'dtrData'));
    }
}
