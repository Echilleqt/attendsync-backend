<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:present,late,absent,incomplete,on_leave'],
        ]);

        $fromDate = $validated['from_date']
            ?? Carbon::today('Asia/Manila')->startOfMonth()->format('Y-m-d');
        $toDate = $validated['to_date']
            ?? Carbon::today('Asia/Manila')->format('Y-m-d');
        $status = $validated['status'] ?? null;

        if ($fromDate > $toDate) {
            throw ValidationException::withMessages([
                'from_date' => 'The start date must be on or before the end date.',
            ]);
        }

        $query = Attendance::with(['employee', 'schedule'])
            ->whereBetween('attendance_date', [
                $fromDate,
                $toDate,
            ])
            ->latest('attendance_date')
            ->latest('am_time_in');

        if ($status && in_array($status, [
            'present',
            'late',
            'absent',
            'incomplete',
            'on_leave',
        ])) {
            $query->where('status', $status);
        }

        $attendances = $query->paginate(25)->withQueryString();

        $summaryQuery = Attendance::query()
            ->whereBetween('attendance_date', [
                $fromDate,
                $toDate,
            ]);

        $presentCount = (clone $summaryQuery)
            ->where('status', 'present')
            ->count();

        $lateCount = (clone $summaryQuery)
            ->where('status', 'late')
            ->count();

        $absentCount = (clone $summaryQuery)
            ->where('status', 'absent')
            ->count();

        $incompleteCount = (clone $summaryQuery)
            ->where('status', 'incomplete')
            ->count();

        $onLeaveCount = (clone $summaryQuery)
            ->where('status', 'on_leave')
            ->count();

        $totalRecords = $presentCount
            + $lateCount
            + $absentCount
            + $incompleteCount
            + $onLeaveCount;

        return view('admin.reports.index', compact(
            'attendances',
            'fromDate',
            'toDate',
            'status',
            'presentCount',
            'lateCount',
            'absentCount',
            'incompleteCount',
            'onLeaveCount',
            'totalRecords'
        ));
    }
}