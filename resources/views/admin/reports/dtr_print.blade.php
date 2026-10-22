<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSC Form No. 48 — {{ $dtrData['employee']['name'] }} ({{ $dtrData['month'] }} {{ $dtrData['year'] }})</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 15mm;
        }
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        .dtr-container {
            max-width: 650px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
        }
        .form-no {
            font-size: 9pt;
            font-style: italic;
        }
        .title {
            font-size: 14pt;
            font-weight: bold;
            margin: 4px 0;
            letter-spacing: 1px;
        }
        .name-line {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-top: 8px;
        }
        .sub-name {
            font-size: 9pt;
        }
        .meta-line {
            font-size: 10pt;
            margin-top: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            font-size: 9pt;
            border: 1px solid #000;
        }
        th, td {
            border: 1px solid #000;
            padding: 3px 2px;
        }
        th {
            font-weight: bold;
        }
        .certification {
            font-size: 8.5pt;
            text-indent: 20px;
            text-align: justify;
            margin-top: 14px;
            line-height: 1.35;
        }
        .signatures {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
        }
        .sig-block {
            text-align: center;
            width: 45%;
        }
        .sig-line {
            border-top: 1px solid #000;
            padding-top: 3px;
            font-weight: bold;
            font-size: 10pt;
        }
        .sig-sub {
            font-size: 8.5pt;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background:#f3f4f6; padding:10px 16px; margin-bottom:20px; text-align:center; border-radius:6px; font-family:sans-serif; font-size:13px;">
        <button onclick="window.print()" style="background:#1f2937; color:#fff; border:none; padding:8px 20px; border-radius:4px; font-weight:bold; cursor:pointer;">Print CSC Form 48 / Save as PDF</button>
        <span style="margin-left:12px; color:#4b5563;">Set layout to Portrait, Margins to Minimum or Default in browser print preview.</span>
    </div>

    <div class="dtr-container">
        <div class="header">
            <div class="form-no">Civil Service Form No. 48</div>
            <div class="title">DAILY TIME RECORD</div>
            <div class="name-line">{{ $dtrData['employee']['name'] }}</div>
            <div class="sub-name">(Name)</div>
            <div class="meta-line">
                For the month of <u><strong>{{ $dtrData['month'] }} {{ $dtrData['year'] }}</strong></u><br>
                Official hours for arrival and departure: <u>{{ $dtrData['prescribed_hours'] }}</u>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width:30px;">Day</th>
                    <th colspan="2">A. M.</th>
                    <th colspan="2">P. M.</th>
                    <th colspan="2">Undertime</th>
                </tr>
                <tr>
                    <th style="width:65px;">Arrival</th>
                    <th style="width:65px;">Departure</th>
                    <th style="width:65px;">Arrival</th>
                    <th style="width:65px;">Departure</th>
                    <th style="width:45px;">Hours</th>
                    <th style="width:45px;">Minutes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dtrData['days'] as $row)
                <tr>
                    <td style="font-weight:bold;">{{ $row['day'] }}@if($row['needs_review'])<sup>*</sup>@endif</td>
                    <td>{{ $row['am_in'] }}</td>
                    <td>{{ $row['am_out'] }}</td>
                    <td>{{ $row['pm_in'] }}</td>
                    <td>{{ $row['pm_out'] }}</td>
                    <td>{{ $row['undertime_hours'] }}</td>
                    <td>{{ $row['undertime_minutes'] }}</td>
                </tr>
                @endforeach
                <tr style="font-weight:bold;">
                    <td colspan="5" style="text-align:right; padding-right:8px;">TOTAL</td>
                    <td>{{ $dtrData['total_undertime_hours'] ?: '' }}</td>
                    <td>{{ $dtrData['total_undertime_minutes'] ?: '' }}</td>
                </tr>
            </tbody>
        </table>

        @if(collect($dtrData['days'])->contains('needs_review', true))
            <div style="font-size:9pt; margin-top:6px;">* Incomplete or mismatched attendance session. Recorded times were not changed; review and resolve the attendance record. Undertime totals are withheld while a record is unresolved.</div>
        @endif

        <div class="certification">
            I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.
        </div>

        <div class="signatures">
            <div class="sig-block" style="margin-top:25px;">
                <div class="sig-line">{{ $dtrData['employee']['name'] }}</div>
                <div class="sig-sub">Employee Signature</div>
            </div>

            <div class="sig-block">
                <div style="font-size:9pt; margin-bottom:25px;">Verified as to the prescribed office hours:</div>
                <div class="sig-line">In-Charge / School Head</div>
                <div class="sig-sub">Bansud Central School</div>
            </div>
        </div>
    </div>
</body>
</html>
