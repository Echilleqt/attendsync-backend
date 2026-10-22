<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'schedule_id',
        'attendance_date',
        'time_in',
        'time_out',
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'status',
        'am_status',
        'pm_status',
        'photo_path',
        'time_out_photo_path',
        'am_time_in_photo_path',
        'am_time_out_photo_path',
        'pm_time_in_photo_path',
        'pm_time_out_photo_path',
        'late_starting_time_snapshot',
        'time_in_verification_method',
        'time_out_verification_method',
        'am_time_in_verification_method',
        'am_time_out_verification_method',
        'pm_time_in_verification_method',
        'pm_time_out_verification_method',
        'undertime_minutes',
    ];

    protected $hidden = [
        'photo_path',
        'time_out_photo_path',
        'am_time_in_photo_path',
        'am_time_out_photo_path',
        'pm_time_in_photo_path',
        'pm_time_out_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'am_time_in' => 'datetime',
            'am_time_out' => 'datetime',
            'pm_time_in' => 'datetime',
            'pm_time_out' => 'datetime',
            'undertime_minutes' => 'integer',
            'late_starting_time_snapshot' => 'string',
        ];
    }

    public function setAttendanceDateAttribute($value): void
    {
        $this->attributes['attendance_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}