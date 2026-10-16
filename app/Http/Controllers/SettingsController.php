<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use App\Models\NetworkRestrictionSetting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display attendance and network settings.
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

        $networkSetting = NetworkRestrictionSetting::current();
        $networkSetting->loadMissing('updater');
        $networkStatusInfo = $networkSetting->evaluateStatus();

        return view('admin.settings.index', compact(
            'currentSetting',
            'settings',
            'networkSetting',
            'networkStatusInfo'
        ));
    }

    /**
     * Update network restriction settings.
     */
    public function updateNetwork(Request $request): RedirectResponse
    {
        $isEnabled = $request->boolean('is_enabled');
        $verificationMethod = (string) $request->input('verification_method', 'client_assisted');

        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'authorized_ssid' => [
                $isEnabled && $verificationMethod === 'client_assisted' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],
            'authorized_bssids' => ['nullable', 'string', 'max:2000'],
            'network_description' => ['nullable', 'string', 'max:255'],
            'verification_method' => [
                'required',
                'string',
                Rule::in(['client_assisted', 'trusted_ip_range', 'school_vpn_or_gateway']),
            ],
            'trusted_ip_ranges' => [
                $isEnabled && in_array($verificationMethod, ['trusted_ip_range', 'school_vpn_or_gateway'], true) ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $setting = NetworkRestrictionSetting::current();

        $setting->is_enabled = $isEnabled;
        $setting->authorized_ssid = isset($validated['authorized_ssid']) && trim((string) $validated['authorized_ssid']) !== '' ? trim((string) $validated['authorized_ssid']) : null;
        $setting->authorized_bssids = isset($validated['authorized_bssids']) && trim((string) $validated['authorized_bssids']) !== '' ? trim((string) $validated['authorized_bssids']) : null;
        $setting->network_description = isset($validated['network_description']) && trim((string) $validated['network_description']) !== '' ? trim((string) $validated['network_description']) : null;
        $setting->verification_method = $validated['verification_method'];
        $setting->trusted_ip_ranges = isset($validated['trusted_ip_ranges']) && trim((string) $validated['trusted_ip_ranges']) !== '' ? trim((string) $validated['trusted_ip_ranges']) : null;
        $setting->updated_by = auth()->id();

        $statusInfo = $setting->evaluateStatus();
        $setting->verification_status = $statusInfo['status'];

        $setting->save();

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Network restriction settings updated successfully.');
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