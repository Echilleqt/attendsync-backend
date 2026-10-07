<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display attendance settings.
     */
    public function index(): View
    {
        $today = Carbon::today();

        $currentSetting = AttendanceSetting::where(
            'effective_date',
            '<=',
            $today
        )
            ->orderByDesc('effective_date')
            ->first();

        $settings = AttendanceSetting::with('creator')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.settings.index', compact(
            'currentSetting',
            'settings'
        ));
    }

    /**
     * Save a new attendance setting.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'late_starting_time' => [
                'required',
                'date_format:H:i',
            ],
            'effective_date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_settings', 'effective_date'),
            ],
        ]);

        AttendanceSetting::create([
            'late_starting_time' => $validated['late_starting_time'],
            'effective_date' => $validated['effective_date'],
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Attendance setting saved successfully.');
    }
}