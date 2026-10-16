<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::withCount('employees')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.schedules.index', compact('schedules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'am_time_in' => ['required', 'date_format:H:i'],
            'am_time_out' => ['required', 'date_format:H:i'],
            'pm_time_in' => ['required', 'date_format:H:i'],
            'pm_time_out' => ['required', 'date_format:H:i'],
            'late_threshold_am' => ['required', 'date_format:H:i'],
            'late_threshold_pm' => ['required', 'date_format:H:i'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            Schedule::query()->update(['is_default' => false]);
        }

        Schedule::create([
            ...$validated,
            'is_default' => !empty($validated['is_default']),
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? 0,
            'work_days' => $validated['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Schedule created successfully.');
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'am_time_in' => ['required'],
            'am_time_out' => ['required'],
            'pm_time_in' => ['required'],
            'pm_time_out' => ['required'],
            'late_threshold_am' => ['required'],
            'late_threshold_pm' => ['required'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            Schedule::where('id', '!=', $schedule->id)->update(['is_default' => false]);
        }

        $schedule->update([
            ...$validated,
            'is_default' => !empty($validated['is_default']),
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? 0,
            'work_days' => $validated['work_days'] ?? $schedule->work_days,
        ]);

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Schedule updated successfully.');
    }

    public function destroy(Schedule $schedule)
    {
        if ($schedule->employees()->count() > 0) {
            return redirect()->route('admin.schedules.index')
                ->with('error', 'Cannot delete schedule with assigned employees. Reassign employees first.');
        }

        $schedule->delete();

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Schedule deleted successfully.');
    }
}
