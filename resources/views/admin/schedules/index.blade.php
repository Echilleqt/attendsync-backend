@extends('layouts.admin')

@section('title', 'Schedules — ATTENDSYNC')

@section('content')
<div class="admin-page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h1 class="page-title">Work Schedules &amp; Shifts</h1>
        <p class="page-subtitle">Configure four-entry daily work shifts and late cutoffs for school personnel.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
        {{ session('error') }}
    </div>
@endif

<div style="display:grid; grid-template-columns: 1fr 380px; gap:20px;">
    <div class="card" style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; overflow:hidden;">
        <table class="table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb; color:#6b7280;">
                    <th style="padding:12px 16px;">Schedule Name</th>
                    <th style="padding:12px 16px;">Morning (AM)</th>
                    <th style="padding:12px 16px;">Afternoon (PM)</th>
                    <th style="padding:12px 16px;">Assigned</th>
                    <th style="padding:12px 16px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $sched)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:12px 16px;">
                        <strong>{{ $sched->name }}</strong>
                        @if($sched->is_default)
                            <span style="background:#d1fae5; color:#065f46; padding:1px 6px; border-radius:4px; font-size:10px; font-weight:700; margin-left:4px;">DEFAULT</span>
                        @endif
                        @if($sched->description)
                            <div style="color:#6b7280; font-size:12px;">{{ $sched->description }}</div>
                        @endif
                    </td>
                    <td style="padding:12px 16px;">
                        <div>{{ \Carbon\Carbon::parse($sched->am_time_in)->format('g:i A') }} – {{ \Carbon\Carbon::parse($sched->am_time_out)->format('g:i A') }}</div>
                        <small style="color:#b45309;">Late after {{ \Carbon\Carbon::parse($sched->late_threshold_am)->format('g:i A') }}</small>
                    </td>
                    <td style="padding:12px 16px;">
                        <div>{{ \Carbon\Carbon::parse($sched->pm_time_in)->format('g:i A') }} – {{ \Carbon\Carbon::parse($sched->pm_time_out)->format('g:i A') }}</div>
                        <small style="color:#b45309;">Late after {{ \Carbon\Carbon::parse($sched->late_threshold_pm)->format('g:i A') }}</small>
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600;">
                            {{ $sched->employees_count }} employees
                        </span>
                    </td>
                    <td style="padding:12px 16px; text-align:right;">
                        @if(!$sched->is_default && $sched->employees_count === 0)
                            <form method="POST" action="{{ route('admin.schedules.destroy', $sched) }}" style="display:inline;" onsubmit="return confirm('Delete schedule {{ $sched->name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#991b1b; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px;">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="padding:32px 16px; text-align:center; color:#9ca3af;">
                        No custom schedules configured yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div style="padding:12px 16px;">
            {{ $schedules->links() }}
        </div>
    </div>

    <div class="card" style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; padding:20px; height:fit-content;">
        <h3 style="font-size:16px; font-weight:700; margin:0 0 16px; color:#111827;">Add 4-Entry Work Schedule</h3>
        <form method="POST" action="{{ route('admin.schedules.store') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">Schedule Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Regular Faculty (Mon-Fri)" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                @error('name') <span style="color:#dc2626; font-size:11px;">{{ $message }}</span> @enderror
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">AM Time In *</label>
                    <input type="time" name="am_time_in" value="{{ old('am_time_in', '08:00') }}" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">AM Time Out *</label>
                    <input type="time" name="am_time_out" value="{{ old('am_time_out', '12:00') }}" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">PM Time In * (Target 1:00 PM)</label>
                    <input type="time" name="pm_time_in" value="{{ old('pm_time_in', '13:00') }}" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">PM Time Out *</label>
                    <input type="time" name="pm_time_out" value="{{ old('pm_time_out', '17:00') }}" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#b45309;">AM Late Threshold *</label>
                    <input type="time" name="late_threshold_am" value="{{ old('late_threshold_am', '08:00') }}" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#b45309;">PM Late Threshold *</label>
                    <input type="time" name="late_threshold_pm" value="{{ old('late_threshold_pm', '13:00') }}" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer;">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                    <span>Set as school default schedule</span>
                </label>
            </div>

            <button type="submit" style="width:100%; background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px;">Save Schedule</button>
        </form>
    </div>
</div>
@endsection
