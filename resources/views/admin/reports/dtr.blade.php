@extends('layouts.admin')

@section('title', 'CSC Form No. 48 (Daily Time Record) — ATTENDSYNC')
@section('page_title', 'CSC Form 48 (Daily Time Record)')
@section('page_description', 'Official 4-entry Philippine Civil Service Commission monthly attendance sheet for Bansud Central School.')

@section('content')
<section class="employee-page">
    <div class="employee-heading">
        <div>
            <span class="school-label">BANSUD CENTRAL SCHOOL</span>
            <p class="employee-context">Official 4-entry Civil Service Form No. 48 monthly DTR generation and review.</p>
        </div>
        @if($selectedEmployee && $dtrData)
            <a href="{{ route('admin.reports.dtr.print', ['employee_id' => $selectedEmployee->id, 'month' => $month]) }}" target="_blank" class="btn-primary">
                <x-lucide-printer class="w-4 h-4" aria-hidden="true" />
                <span>Print / Save CSC Form 48</span>
            </a>
        @endif
    </div>

    <div class="employee-panel report-filter-panel">
        <form method="GET" action="{{ route('admin.reports.dtr') }}" class="report-filter-form">
            <div class="report-filter-field filter-grow">
                <label for="dtr_emp_id">Select Employee</label>
                <select id="dtr_emp_id" name="employee_id" onchange="this.form.submit()">
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ $selectedEmployee && $selectedEmployee->id === $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }} ({{ $emp->employee_id }}) — {{ $emp->position }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="report-filter-field filter-date">
                <label for="dtr_month">Month &amp; Year</label>
                <input type="month" id="dtr_month" name="month" value="{{ $month }}" onchange="this.form.submit()">
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="btn-primary">
                    <x-lucide-file-spreadsheet class="w-4 h-4" aria-hidden="true" />
                    <span>Generate DTR</span>
                </button>
            </div>
        </form>
    </div>

    @if($dtrData)
    <div class="employee-panel dtr-preview-panel">
        <div class="dtr-paper-wrapper">
            <div class="dtr-paper-header">
                <span class="dtr-form-no">Civil Service Form No. 48</span>
                <h2 class="dtr-paper-title">DAILY TIME RECORD</h2>
                <div class="dtr-employee-name-print">
                    {{ $dtrData['employee']['name'] }}
                </div>
                <div class="dtr-name-sub">(Name in Print)</div>
                <div class="dtr-period-line">
                    For the month of <strong>{{ $dtrData['month'] }} {{ $dtrData['year'] }}</strong>
                </div>
                <div class="dtr-hours-line">
                    Official hours for arrival and departure: <strong>{{ $dtrData['prescribed_hours'] }}</strong>
                </div>
            </div>

            <div class="table-responsive">
                <table class="dtr-paper-table">
                    <thead>
                        <tr class="dtr-table-head-top">
                            <th rowspan="2" class="dtr-col-day">Day</th>
                            <th colspan="2">A. M.</th>
                            <th colspan="2">P. M.</th>
                            <th colspan="2">Undertime</th>
                        </tr>
                        <tr class="dtr-table-head-sub">
                            <th class="dtr-col-time">Arrival</th>
                            <th class="dtr-col-time">Departure</th>
                            <th class="dtr-col-time">Arrival</th>
                            <th class="dtr-col-time">Departure</th>
                            <th class="dtr-col-under">Hours</th>
                            <th class="dtr-col-under">Minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dtrData['days'] as $row)
                        <tr class="{{ $row['is_weekend'] ? 'dtr-row-weekend' : '' }}">
                            <td class="font-semibold">{{ $row['day'] }}@if($row['needs_review'])<sup>*</sup>@endif</td>
                            <td>{{ $row['am_in'] }}</td>
                            <td>{{ $row['am_out'] }}</td>
                            <td>{{ $row['pm_in'] }}</td>
                            <td>{{ $row['pm_out'] }}</td>
                            <td>{{ $row['undertime_hours'] }}</td>
                            <td>{{ $row['undertime_minutes'] }}</td>
                        </tr>
                        @endforeach
                        <tr class="dtr-row-total">
                            <td colspan="5" class="dtr-total-label">TOTAL:</td>
                            <td>{{ $dtrData['total_undertime_hours'] ?: '' }}</td>
                            <td>{{ $dtrData['total_undertime_minutes'] ?: '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if(collect($dtrData['days'])->contains('needs_review', true))
                <p class="text-sm mt-2">* Incomplete or mismatched attendance session. The recorded times were not changed; please review and resolve the attendance record. Undertime totals are withheld while a record is unresolved.</p>
            @endif

            <div class="dtr-paper-certification">
                <p>
                    I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.
                </p>

                <div class="dtr-signatures-row">
                    <div class="dtr-sig-box">
                        <div class="dtr-sig-line">
                            {{ $dtrData['employee']['name'] }}
                        </div>
                        <div class="dtr-sig-caption">Employee Signature</div>
                    </div>

                    <div class="dtr-sig-box">
                        <div class="dtr-verified-text">Verified as to the prescribed office hours:</div>
                        <div class="dtr-sig-line">
                            In-Charge / School Head
                        </div>
                        <div class="dtr-sig-caption">Bansud Central School</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</section>
@endsection
