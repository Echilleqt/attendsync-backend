<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_date',
        'request_type',
        'requested_time_in',
        'requested_time_out',
        'reason',
        'status',
        'admin_review_notes',
        'reviewed_by',
        'reviewed_at',
        'original_values',
        'approved_values',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'reviewed_at' => 'datetime',
            'original_values' => 'array',
            'approved_values' => 'array',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
