<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'late_threshold_am',
        'late_threshold_pm',
        'grace_period_minutes',
        'work_days',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'is_default' => 'boolean',
            'grace_period_minutes' => 'integer',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
