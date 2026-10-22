# Attendance data integrity

## Authoritative data

New attendance submissions use the server's current time in `Asia/Manila` and
write the explicit `am_time_in`, `am_time_out`, `pm_time_in`, and
`pm_time_out` columns. The applicable schedule is resolved for the employee
and attendance date from schedule assignments, direct assignments, or the
configured default. The legacy `time_in` and `time_out` columns remain
compatibility fields; they are not reliable substitutes for the four session
events in calculations or reports.

The generic mobile `time-out` route has a narrowly-scoped compatibility path
for existing legacy-only rows: it is eligible only when a legacy `time_in`
exists and all four session columns are empty. Its Time Out must be after the
legacy Time In and within the configured afternoon session. This path writes
only the legacy Time Out and proof metadata; it does not invent a PM Time In,
populate a DTR departure, or calculate session undertime. New four-entry rows
do not use this compatibility path.

## Corrected failure modes

- The attendance model previously inferred a morning/afternoon event from a
  hardcoded noon boundary when a legacy timestamp was assigned. That could
  misclassify events for schedules with different session boundaries and could
  copy an event into a second field. Event fields are now independent; callers
  that need a legacy compatibility value must set it explicitly.
- The DTR previously fell back to legacy event fields, used literal schedule
  hours when no schedule was found, and added late-arrival minutes to the
  Undertime columns. It now reads only explicit event fields, resolves each
  day's schedule, and accumulates valid early departures only. A missing
  schedule is shown as unconfigured instead of guessed.
- Undertime is not calculated from a missing departure. Out-of-order,
  cross-date, and out-of-session pairs do not contribute to undertime.
- A timestamp filed in the wrong scheduled session makes that session
  incomplete. The DTR omits the invalid timestamp, marks the day for review,
  and withholds daily and monthly undertime totals until the record is
  resolved. The original timestamp and proof are preserved.
- Undertime differences are calculated at the same whole-minute precision
  shown on the DTR. The event timestamps retain their seconds in storage, but
  hidden seconds no longer make a displayed 4:20 departure count as only 9
  minutes before a 4:30 schedule end.
- Attendance correction approval previously assigned timestamps to sessions
  using only coarse AM/PM comparisons. Corrections now require an applicable
  schedule, map into a configured session, and require a matching Time In for
  a Time Out.
- The absence command was already registered in Laravel's scheduler. A
  missed AM session before the PM deadline was previously recorded as a
  whole-day absence; it is now an incomplete day until the PM session is
  evaluated. The service has a dry-run option for previewing absence changes.

## Historical inspection

Run the audit without changing attendance data:

```text
php artisan attendance:audit-repairs --from=YYYY-MM-DD --to=YYYY-MM-DD
```

Optionally limit the audit with `--employee=<employee database ID>`. The
command writes a JSON report to the private local storage disk. Candidate
values are based on a legacy event's calendar date and its applicable
configured schedule. Any conflict, missing schedule, or event that cannot be
placed in one unique session is reported for administrator review. The report
is read-only: it does not update historical attendance, proof paths, or
correction audit records. There is deliberately no bulk-apply command; an
administrator must review the evidence and use the existing correction
approval workflow.

To preview absence processing without saving changes:

```text
php artisan attendance:process-absences --date=YYYY-MM-DD --dry-run
```

To preview stored undertime corrections across attendance history:

```text
php artisan attendance:repair-undertime
```

After reviewing the proposed original/proposed minutes, apply only those
calculated values with:

```text
php artisan attendance:repair-undertime --apply
```

This operation changes only `undertime_minutes`, inside row-locked
transactions. It does not change timestamps, attendance statuses, proof
photos, schedules, or correction requests. It derives undertime only from
complete, correctly ordered events that fall within their scheduled session;
incomplete or invalid event pairs contribute zero.

To preview derived status corrections, including wrong-session events:

```text
php artisan attendance:repair-statuses --id=<attendance database ID>
```

Apply only after reviewing the proposed status and undertime values:

```text
php artisan attendance:repair-statuses --id=<attendance database ID> --apply
```

The command defaults to a dry run and updates only the schedule ID and
derived AM, PM, overall status, and undertime fields in a row-locked
transaction. It never modifies recorded timestamps, proof photos, or
correction requests. Date ranges can be selected with `--from` and `--to`.

## Scheduled execution

Laravel registers `attendance:process-absences` every minute in
`Asia/Manila`. On the Windows/XAMPP deployment, keep Laravel's scheduler
running, for example with a supervised process:

```text
php artisan schedule:work
```

Alternatively, configure Windows Task Scheduler to invoke
`php artisan schedule:run` every minute from the project directory. Confirm
registration with `php artisan schedule:list` and inspect
`storage/logs/absence-processing.log`. Code registration alone does not prove
that the host scheduler is running.

## Policy and deployment limitations

There is no holiday calendar or standalone approved-leave source in this
repository; only an existing attendance record with `on_leave` is respected.
No official session-level absence policy beyond the configured session ends
was found. The school must confirm how holidays, approved leave, and
session-level absence should be represented before those integrations can be
implemented.

Historical corrections should be previewed and applied by attendance ID only
after reviewing the supporting evidence. The host scheduler's execution has
not been verified.
