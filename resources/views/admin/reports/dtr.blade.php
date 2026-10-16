@extends('layouts.admin')

@section('title', 'CSC Form No. 48 (Daily Time Record) — ATTENDSYNC')

@section('content')
<div class="admin-page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h1 class="page-title">Civil Service Form No. 48 / Daily Time Record (DTR)</h1>
        <p class="page-subtitle">Official 4-entry Philippine Civil Service Commission monthly attendance sheet for Bansud Central School.</p>
    </div>
    @if($selectedEmployee && $dtrData)
        <a href="{{ route('admin.reports.dtr.print', ['employee_id' => $selectedEmployee->id, 'month' => $month]) }}" target="_blank" style="background:#059669; color:#fff; text-decoration:none; padding:10px 18px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:8px;">
            <svg viewBox="0 0 24 24" style="width:16px; height:16px; fill:none; stroke:currentColor; stroke-width:2;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save CSC Form 48
        </a>
    @endif
</div>

<div class="filter-card" style="background:#fff; padding:16px 20px; border-radius:10px; border:1px solid #e5e7eb; margin-bottom:20px;">
    <form method="GET" action="{{ route('admin.reports.dtr') }}" style="display:flex; gap:16px; align-items:flex-end;">
        <div style="flex:1;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">Select Employee</label>
            <select name="employee_id" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;" onchange="this.form.submit()">
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ $selectedEmployee && $selectedEmployee->id === $emp->id ? 'selected' : '' }}>
                        {{ $emp->full_name }} ({{ $emp->employee_id }}) — {{ $emp->position }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="width:180px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">Month &amp; Year</label>
            <input type="month" name="month" value="{{ $month }}" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;" onchange="this.form.submit()">
        </div>

        <button type="submit" style="background:#1f2937; color:#fff; border:none; padding:8px 18px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px;">Generate DTR</button>
    </form>
</div>

@if($dtrData)
<div class="card" style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; padding:24px; max-width:850px; margin:0 auto; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="text-align:center; margin-bottom:16px; border-bottom:2px solid #111827; padding-bottom:12px;">
        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#6b7280;">Civil Service Form No. 48</div>
        <h2 style="font-size:18px; font-weight:800; margin:4px 0; text-transform:uppercase; letter-spacing:0.5px;">DAILY TIME RECORD</h2>
        <div style="font-size:14px; font-weight:700; color:#111827; text-decoration:underline; text-transform:uppercase; margin-top:8px;">
            {{ $dtrData['employee']['name'] }}
        </div>
        <div style="font-size:12px; color:#4b5563;">(Name in Print)</div>
        <div style="margin-top:6px; font-size:13px;">
            For the month of <strong>{{ $dtrData['month'] }} {{ $dtrData['year'] }}</strong>
        </div>
        <div style="font-size:11px; color:#6b7280; margin-top:2px;">
            Official hours for arrival and departure: <strong>{{ $dtrData['prescribed_hours'] }}</strong>
        </div>
    </div>

    <table style="width:100%; border-collapse:collapse; text-align:center; font-size:12px; border:1px solid #000;">
        <thead>
            <tr style="background:#f3f4f6; border-bottom:1px solid #000;">
                <th rowspan="2" style="border:1px solid #000; padding:6px 4px; width:40px;">Day</th>
                <th colspan="2" style="border:1px solid #000; padding:4px;">A. M.</th>
                <th colspan="2" style="border:1px solid #000; padding:4px;">P. M.</th>
                <th colspan="2" style="border:1px solid #000; padding:4px;">Undertime</th>
            </tr>
            <tr style="background:#f9fafb; border-bottom:1px solid #000;">
                <th style="border:1px solid #000; padding:4px 6px; width:80px;">Arrival</th>
                <th style="border:1px solid #000; padding:4px 6px; width:80px;">Departure</th>
                <th style="border:1px solid #000; padding:4px 6px; width:80px;">Arrival</th>
                <th style="border:1px solid #000; padding:4px 6px; width:80px;">Departure</th>
                <th style="border:1px solid #000; padding:4px 6px; width:50px;">Hours</th>
                <th style="border:1px solid #000; padding:4px 6px; width:50px;">Minutes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dtrData['days'] as $row)
            <tr style="border-bottom:1px solid #d1d5db; {{ $row['is_weekend'] ? 'background:#fbfbfb; color:#9ca3af;' : '' }}">
                <td style="border:1px solid #000; padding:3px 4px; font-weight:600;">{{ $row['day'] }}</td>
                <td style="border:1px solid #000; padding:3px 4px;">{{ $row['am_in'] }}</td>
                <td style="border:1px solid #000; padding:3px 4px;">{{ $row['am_out'] }}</td>
                <td style="border:1px solid #000; padding:3px 4px;">{{ $row['pm_in'] }}</td>
                <td style="border:1px solid #000; padding:3px 4px;">{{ $row['pm_out'] }}</td>
                <td style="border:1px solid #000; padding:3px 4px;">{{ $row['undertime_hours'] }}</td>
                <td style="border:1px solid #000; padding:3px 4px;">{{ $row['undertime_minutes'] }}</td>
            </tr>
            @endforeach
            <tr style="background:#f3f4f6; font-weight:700; border-top:2px solid #000;">
                <td colspan="5" style="border:1px solid #000; padding:6px 12px; text-align:right;">TOTAL:</td>
                <td style="border:1px solid #000; padding:6px 4px;">{{ $dtrData['total_undertime_hours'] ?: '' }}</td>
                <td style="border:1px solid #000; padding:6px 4px;">{{ $dtrData['total_undertime_minutes'] ?: '' }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:20px; font-size:11px; line-height:1.5; color:#374151;">
        <p style="text-indent:24px; margin-bottom:24px; font-style:italic;">
            I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.
        </p>

        <div style="display:flex; justify-content:space-between; margin-top:24px;">
            <div style="text-align:center; width:240px;">
                <div style="border-top:1px solid #000; padding-top:4px; font-weight:700; text-transform:uppercase;">
                    {{ $dtrData['employee']['name'] }}
                </div>
                <div style="font-size:10px; color:#6b7280;">Employee Signature</div>
            </div>

            <div style="text-align:center; width:240px;">
                <div style="font-size:11px; margin-bottom:20px;">Verified as to the prescribed office hours:</div>
                <div style="border-top:1px solid #000; padding-top:4px; font-weight:700;">
                    In-Charge / School Head
                </div>
                <div style="font-size:10px; color:#6b7280;">Bansud Central School</div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
